<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\DocumentChunk;
use App\Models\LegalDocument;
use App\Services\ArabicTextNormalizerService;
use App\Services\ClaudeService;
use App\Services\TasbibatKnowledgeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * AssistantController
 * ───────────────────
 * Powers the AI chat panel on the document show page.
 *
 * Endpoints:
 *   POST /ai/conversations                       — start a new conversation (optionally bound to a document)
 *   GET  /ai/conversations/{conversation}        — fetch conversation + messages
 *   POST /ai/conversations/{conversation}/messages — send a user message, get an assistant reply
 *
 * RAG strategy: when the conversation is bound to a document, we either send
 * the full document content (if small) or pick the top-K matching chunks
 * via DB LIKE on the normalized form. No embeddings — Layer E v1 keeps it
 * lightweight; the model itself does the relevance reasoning.
 */
class AssistantController extends Controller
{
    use AuthorizesRequests;

    /** Max characters of full document content to inline before falling back to chunk RAG. */
    private const FULL_DOC_INLINE_LIMIT = 50000;

    /** How many chunks to retrieve when doing RAG. */
    private const RAG_TOP_K = 6;

    public function __construct(
        private ClaudeService $claude,
        private ArabicTextNormalizerService $normalizer,
        private TasbibatKnowledgeService $tasbibat,
    ) {}

    public function start(Request $request): JsonResponse
    {
        if (! $this->claude->isConfigured()) {
            return $this->aiUnavailable();
        }

        $data = $request->validate([
            'document_id' => 'nullable|integer|exists:legal_documents,id',
            'title'       => 'nullable|string|max:255',
        ]);

        // If a document was passed, make sure the user can read it
        if (! empty($data['document_id'])) {
            $document = LegalDocument::findOrFail($data['document_id']);
            $this->authorize('view', $document);
        }

        $conversation = AiConversation::create([
            'user_id'     => $request->user()->id,
            'document_id' => $data['document_id'] ?? null,
            'title'       => $data['title'] ?? null,
        ]);

        return response()->json([
            'conversation' => [
                'id'          => $conversation->id,
                'document_id' => $conversation->document_id,
                'title'       => $conversation->title,
                'messages'    => [],
            ],
        ]);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $messages = $conversation->messages()->get(['id', 'role', 'content', 'created_at']);

        return response()->json([
            'conversation' => [
                'id'          => $conversation->id,
                'document_id' => $conversation->document_id,
                'title'       => $conversation->title,
                'messages'    => $messages,
            ],
        ]);
    }

    public function sendMessage(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        if (! $this->claude->isConfigured()) {
            return $this->aiUnavailable();
        }

        $data = $request->validate([
            'content' => 'required|string|min:1|max:5000',
        ]);

        // 1. Persist the user message immediately so it's never lost on a failed reply
        $userMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => AiMessage::ROLE_USER,
            'content'         => $data['content'],
        ]);

        // 2. Build the document context (if any) and the system prompt
        [$systemPrompt, $contextChunkIds] = $this->buildContext($conversation, $data['content']);

        // 3. Replay prior messages to maintain conversation continuity
        $apiMessages = $conversation->messages()
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        try {
            $result = $this->claude->chat(
                messages: $apiMessages,
                system: $systemPrompt,
                maxTokens: 2048,
            );
        } catch (RuntimeException $e) {
            // Roll back the user message? No — keep it so the user can retry
            // and so they can see what they asked. Just surface the error.
            return response()->json([
                'error' => $e->getMessage(),
                'user_message' => $this->serializeMessage($userMessage),
            ], 502);
        }

        // 4. Persist the assistant reply
        $assistantMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => AiMessage::ROLE_ASSISTANT,
            'content'         => $result['text'],
            'context'         => $contextChunkIds,
            'input_tokens'    => $result['input_tokens'],
            'output_tokens'   => $result['output_tokens'],
        ]);

        return response()->json([
            'user_message'      => $this->serializeMessage($userMessage),
            'assistant_message' => $this->serializeMessage($assistantMessage),
        ]);
    }

    /**
     * Build the system prompt + context for Claude. When the conversation is
     * bound to a document we either inline the full content (small docs) or
     * pick top-K matching chunks (large docs).
     *
     * @return array{0:string, 1:array<int>}  [system prompt, used chunk IDs]
     */
    private function buildContext(AiConversation $conversation, string $userQuery): array
    {
        $arabicRule = "تعليمات صارمة: يجب أن تكتب إجابتك بالكامل باللغة العربية الفصحى. لا تستخدم اللغة الإنجليزية أبداً. لا تترجم المصطلحات للإنجليزية. كل كلمة في إجابتك يجب أن تكون عربية.";

        if (! $conversation->document_id) {
            $tasbibatCtx = $this->tasbibat->buildContextFor($userQuery, topK: 3);
            $sys = "أنت مساعد قانوني محترف متخصص في الأنظمة السعودية. $arabicRule";
            if ($tasbibatCtx !== '') {
                $sys .= "\n\n" . $tasbibatCtx;
            }
            return [$sys, []];
        }

        $document = $conversation->document;
        if (! $document) {
            return ["أنت مساعد قانوني محترف. $arabicRule", []];
        }

        $base = "أنت مساعد قانوني متخصص يعمل على وثيقة قانونية سعودية بعنوان \"{$document->title}\" "
              . "(النوع: {$document->type_label}). أجب على أسئلة المستخدم بناءً فقط على المحتوى المرفق أدناه. "
              . "إذا لم تجد إجابة في الوثيقة، قل ذلك صراحة. اقتبس رقم المادة المرجعية عند الإمكان.\n"
              . "$arabicRule\n\n";

        $contentLen = mb_strlen((string) $document->content);
        if ($contentLen > 0 && $contentLen <= self::FULL_DOC_INLINE_LIMIT) {
            // Small document — inline the whole thing
            return [
                $base . "═══ نص الوثيقة الكامل ═══\n" . $document->content,
                [],
            ];
        }

        // Large document — fall back to chunk RAG
        $chunks = $this->retrieveTopChunks($document->id, $userQuery);
        if ($chunks->isEmpty()) {
            return [
                $base . "(لم يتم تحميل أي مقاطع من الوثيقة كسياق. تصرّف وفقاً لذلك وأخبر المستخدم بأن الوثيقة فارغة أو لم يُعثر على مقاطع مطابقة.)",
                [],
            ];
        }

        $context = "═══ مقاطع ذات صلة من الوثيقة ═══\n\n";
        foreach ($chunks as $chunk) {
            $label = $chunk->label ? "[{$chunk->label}] " : '';
            $context .= $label . $chunk->content . "\n\n---\n\n";
        }

        return [$base . $context, $chunks->pluck('id')->all()];
    }

    /**
     * Pick the top-K chunks from a document that look relevant to the user's
     * query. We do a normalized LIKE on each token with at least 2 chars and
     * count distinct token hits per chunk to rank.
     */
    private function retrieveTopChunks(int $documentId, string $query)
    {
        $normalized = $this->normalizer->normalize($query);
        $tokens = collect(preg_split('/\s+/u', $normalized) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 2)
            ->take(8) // hard cap so we don't generate a 50-clause WHERE
            ->values();

        $q = DocumentChunk::query()
            ->where('document_id', $documentId);

        if ($tokens->isEmpty()) {
            // No usable tokens — just return the first few chunks so the
            // model has *something* to work with.
            return $q->orderBy('chunk_index')->limit(self::RAG_TOP_K)->get();
        }

        $q->where(function ($w) use ($tokens) {
            foreach ($tokens as $tok) {
                $w->orWhere('normalized', 'like', "%{$tok}%");
            }
        });

        return $q->orderBy('chunk_index')->limit(self::RAG_TOP_K)->get();
    }

    private function serializeMessage(AiMessage $m): array
    {
        return [
            'id'         => $m->id,
            'role'       => $m->role,
            'content'    => $m->content,
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }

    private function aiUnavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'ميزة AI غير متاحة. يجب ضبط ANTHROPIC_API_KEY في الخادم.',
        ], 503);
    }
}
