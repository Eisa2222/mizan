<?php

namespace App\Jobs;

use App\Models\LegalDocument;
use App\Services\ClaudeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GenerateSuggestedQuestionsJob
 * ─────────────────────────────
 * Optional background job that asks Claude for 3-5 starter questions about
 * a document, stored in metadata.suggested_questions for the chat panel.
 *
 * Silent failure: this is a UX nicety, not a critical path. If anything
 * goes wrong, we just log and skip — no notifications, no error markers.
 */
class GenerateSuggestedQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public LegalDocument $document) {}

    public function handle(ClaudeService $claude): void
    {
        $document = $this->document->fresh();
        if (! $document) return;

        if (! $claude->isConfigured()) return;

        $content = trim((string) $document->content);
        if ($content === '') return;

        // Cap to 30K chars — questions need only a high-level grasp
        $content = mb_substr($content, 0, 30000);

        $system = <<<'PROMPT'
أنت مساعد قانوني. مهمتك توليد 4 أسئلة ابتدائية شائعة قد يطرحها المستخدم على هذه الوثيقة القانونية.
الأسئلة يجب أن تكون:
  - باللغة العربية الفصحى
  - مختصرة (لا تتجاوز 12 كلمة)
  - عملية ومفيدة
  - متنوعة (تغطي جوانب مختلفة من الوثيقة)

أرجع كائن JSON بهذا الشكل بالضبط:
{ "questions": ["السؤال 1", "السؤال 2", "السؤال 3", "السؤال 4"] }
PROMPT;

        $userMessage = "الوثيقة بعنوان \"{$document->title}\" — النوع: {$document->type_label}\n\nالمحتوى:\n" . $content;

        try {
            $result = $claude->chatJson(
                messages: [['role' => 'user', 'content' => $userMessage]],
                system: $system,
                maxTokens: 600,
            );
        } catch (Throwable $e) {
            Log::info('GenerateSuggestedQuestionsJob: skipped', [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);
            return;
        }

        $questions = $result['data']['questions'] ?? [];
        if (! is_array($questions) || empty($questions)) {
            return;
        }

        $meta = $document->metadata ?? [];
        $meta['suggested_questions'] = array_slice(array_values(array_filter($questions, 'is_string')), 0, 5);
        $document->metadata = $meta;
        $document->save();
    }
}
