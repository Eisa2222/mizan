<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * ClaudeService (multi-backend)
 * ─────────────────────────────
 * Unified AI chat wrapper that supports two backends:
 *
 *   1. **Ollama** (local, free) — when AI_PROVIDER=ollama in .env.
 *      Uses the OpenAI-compatible endpoint at http://localhost:11434.
 *      Recommended model: llama3 or llama3.2.
 *
 *   2. **Anthropic Claude** (cloud, paid) — when AI_PROVIDER=anthropic.
 *      Uses the Anthropic Messages API with ANTHROPIC_API_KEY.
 *
 * The rest of the app (AssistantController, SemanticSearchService, analyzer
 * jobs) call chat() / chatJson() without knowing which backend runs.
 *
 * isConfigured() returns true when the selected provider is ready to use.
 */
class ClaudeService
{
    public function __construct() {}

    /** Which backend: 'ollama' or 'anthropic'. */
    private function provider(): string
    {
        return strtolower(config('services.ai.provider', 'ollama'));
    }

    public function isConfigured(): bool
    {
        return match ($this->provider()) {
            'ollama'    => ! empty(config('services.ai.ollama_url')),
            'anthropic' => ! empty(config('services.anthropic.api_key')),
            default     => false,
        };
    }

    /**
     * Send a chat completion request.
     *
     * @param  array  $messages  An array of {role, content} pairs.
     * @param  string|null  $system  Optional system prompt.
     * @param  int  $maxTokens  Max output tokens.
     * @return array{text:string, input_tokens:int, output_tokens:int, raw:array}
     */
    public function chat(array $messages, ?string $system = null, int $maxTokens = 2048): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ميزة AI غير متاحة. تحقق من إعدادات AI_PROVIDER في ملف .env.');
        }

        return match ($this->provider()) {
            'ollama'    => $this->chatOllama($messages, $system, $maxTokens),
            'anthropic' => $this->chatAnthropic($messages, $system, $maxTokens),
            default     => throw new RuntimeException('مزوّد AI غير معروف: ' . $this->provider()),
        };
    }

    /**
     * Convenience: ask for a JSON object response.
     */
    public function chatJson(array $messages, string $system, int $maxTokens = 2048): array
    {
        $reinforcedSystem = $system
            . "\n\nأرجع إجابتك ككائن JSON صالح فقط، بدون أي نص قبله أو بعده. لا تستخدم Markdown أو ``` code fences.";

        $result = $this->chat($messages, $reinforcedSystem, $maxTokens);
        $text = trim($result['text']);

        // Many local models (llama3, etc.) wrap JSON in fences, add preamble
        // text, or mix Arabic prose with the JSON object. We try progressively
        // more aggressive extraction before giving up.

        // 1. Strip ```json fences
        if (str_contains($text, '```')) {
            $text = preg_replace('/^.*?```(?:json)?\s*/su', '', $text);
            $text = preg_replace('/\s*```.*$/su', '', $text);
            $text = trim((string) $text);
        }

        // 2. Try direct decode
        $decoded = json_decode($text, true);

        // 3. If that failed, try to extract the first JSON object from the text
        if (! is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/u', $text, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        // 4. Still failed — try fixing common issues (trailing commas, etc.)
        if (! is_array($decoded)) {
            $cleaned = preg_replace('/,\s*([\]}])/u', '$1', $text); // trailing commas
            if (preg_match('/\{[\s\S]*\}/u', $cleaned ?? '', $m)) {
                $decoded = json_decode($m[0], true);
            }
        }

        if (! is_array($decoded)) {
            Log::warning('ClaudeService: response was not valid JSON', ['text' => mb_substr($text, 0, 500)]);
            throw new RuntimeException('الرد من AI لم يكن JSON صالحاً.');
        }

        return [
            'data'          => $decoded,
            'input_tokens'  => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // Ollama backend (OpenAI-compatible API)
    // ════════════════════════════════════════════════════════════════

    private function chatOllama(array $messages, ?string $system, int $maxTokens): array
    {
        $baseUrl = rtrim(config('services.ai.ollama_url', 'http://localhost:11434'), '/');
        $model   = config('services.ai.ollama_model', 'llama3');

        // Ollama uses OpenAI format: system is just a message with role=system
        $apiMessages = [];
        if ($system !== null && $system !== '') {
            $apiMessages[] = ['role' => 'system', 'content' => $system];
        }
        foreach ($messages as $m) {
            $apiMessages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        $payload = [
            'model'    => $model,
            'messages' => $apiMessages,
            'stream'   => false,
            'options'  => [
                'num_predict' => $maxTokens,
            ],
        ];

        try {
            $response = Http::timeout(180)
                ->post($baseUrl . '/api/chat', $payload);

            if (! $response->successful()) {
                Log::error('ClaudeService [Ollama]: request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new RuntimeException(sprintf(
                    'فشل الاتصال بـ Ollama (HTTP %d). تأكد أن Ollama شغّال على %s.',
                    $response->status(),
                    $baseUrl
                ));
            }

            $body = $response->json();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('ClaudeService [Ollama]: HTTP exception', ['error' => $e->getMessage()]);
            throw new RuntimeException('تعذّر الاتصال بـ Ollama: ' . $e->getMessage());
        }

        // Ollama response format: { message: { role, content }, ... }
        $text = $body['message']['content'] ?? '';

        return [
            'text'          => trim($text),
            'input_tokens'  => (int) ($body['prompt_eval_count'] ?? 0),
            'output_tokens' => (int) ($body['eval_count'] ?? 0),
            'raw'           => $body,
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // Anthropic Claude backend
    // ════════════════════════════════════════════════════════════════

    private function chatAnthropic(array $messages, ?string $system, int $maxTokens): array
    {
        $payload = [
            'model'      => config('services.anthropic.model', 'claude-haiku-4-5'),
            'max_tokens' => $maxTokens,
            'messages'   => $messages,
        ];
        if ($system !== null && $system !== '') {
            $payload['system'] = $system;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.api_key'),
                'anthropic-version' => config('services.anthropic.version', '2023-06-01'),
                'content-type'      => 'application/json',
            ])
                ->timeout(120)
                ->post(rtrim(config('services.anthropic.base_url', 'https://api.anthropic.com/v1'), '/') . '/messages', $payload);

            if (! $response->successful()) {
                Log::error('ClaudeService [Anthropic]: request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new RuntimeException(sprintf(
                    'فشل الاتصال بخدمة Claude (HTTP %d). راجع السجلات.',
                    $response->status()
                ));
            }

            $body = $response->json();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('ClaudeService [Anthropic]: HTTP exception', ['error' => $e->getMessage()]);
            throw new RuntimeException('خطأ شبكة أثناء الاتصال بـ Claude: ' . $e->getMessage());
        }

        // Anthropic response format: { content: [{type: "text", text: "..."}], usage: {...} }
        $blocks = $body['content'] ?? [];
        $parts = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text' && isset($block['text'])) {
                $parts[] = $block['text'];
            }
        }

        return [
            'text'          => trim(implode("\n", $parts)),
            'input_tokens'  => (int) ($body['usage']['input_tokens']  ?? 0),
            'output_tokens' => (int) ($body['usage']['output_tokens'] ?? 0),
            'raw'           => $body,
        ];
    }
}
