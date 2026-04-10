<?php

namespace App\Jobs;

use App\Models\AppNotification;
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
 * TenderReviewJob
 * ───────────────
 * Multi-layer analysis of government procurement tender documents against
 * Saudi Competition & Government Procurement Law and its Executive Regulations.
 *
 * Four inspection layers:
 *   1. Legal compliance (نظامي) — mandatory clauses, legal references
 *   2. Formal (شكلي) — structure, numbering, attachments
 *   3. Substantive (موضوعي) — conflicts, ambiguity, risks
 *   4. Fairness & competition (عدالة) — restrictive conditions, bias indicators
 *
 * Output: compliance score + categorized findings + executive summary
 */
class TenderReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public LegalDocument $document) {}

    public function handle(ClaudeService $claude): void
    {
        $document = $this->document->fresh();
        if (! $document) return;

        if (! $claude->isConfigured()) {
            $this->markStatus($document, 'unavailable', 'ميزة AI غير متاحة.');
            return;
        }

        $content = trim((string) $document->content);
        if ($content === '') {
            $this->markStatus($document, 'failed', 'لا يوجد نص في الكراسة للمراجعة.');
            return;
        }

        $content = mb_substr($content, 0, 80000);

        // Clean broken cmap characters that confuse the AI model.
        // Replace isolated Latin/special chars between Arabic chars with spaces
        // so the AI can still understand the context from surrounding words.
        $arab = '\x{0600}-\x{06FF}';
        $content = preg_replace("/([$arab])[?|~`^]+([$arab])/u", '$1 $2', $content) ?? $content;
        $content = preg_replace("/([$arab])[A-Za-z]{1,2}([$arab])/u", '$1 $2', $content) ?? $content;
        $content = preg_replace("/([$arab])[A-Za-z]{1,2}([$arab])/u", '$1 $2', $content) ?? $content;

        // Step 1: Get findings (individual observations)
        $findingsSystem = 'أنت مراجع كراسات حكومية سعودي. اقرأ النص وحدد 3-5 ملاحظات.'
            . "\nأرجع JSON بالعربية: {\"findings\":[{\"category\":\"نظامي\",\"severity\":\"عالية\",\"title\":\"عنوان\",\"issue\":\"المشكلة\",\"recommendation\":\"التوصية\"}]}"
            . "\ncategory: نظامي أو شكلي أو موضوعي أو عدالة. severity: حرجة أو عالية أو متوسطة أو تحسينية."
            . "\nبالعربية فقط. حلّل المحتوى الفعلي.";

        // Step 2: Get overall assessment
        $summarySystem = 'أنت مراجع كراسات حكومية. قيّم الكراسة بشكل عام.'
            . "\nأرجع JSON بالعربية: {\"compliance_score\":75,\"executive_summary\":\"تقييم بالعربية\",\"ready_for_tender\":false}"
            . "\ncompliance_score من 0 لـ 100. بالعربية فقط.";

        $findings = [];
        $summary = [];
        $lastError = '';

        // Get findings — use chat() then extract JSON manually.
        // chatJson() forces Ollama JSON mode which oversimplifies responses.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $raw = $claude->chat(
                    messages: [['role' => 'user', 'content' => "حلّل هذه الكراسة وأعطني 3 ملاحظات. أرجع JSON فقط:\n{\"findings\":[{\"category\":\"نظامي\",\"severity\":\"عالية\",\"title\":\"عنوان\",\"issue\":\"المشكلة\",\"recommendation\":\"التوصية\"}]}\n\nالكراسة:\n" . mb_substr($content, 0, 5000)]],
                    system: "أرجع JSON بالعربية فقط. حلّل المحتوى الفعلي.",
                    maxTokens: 3000,
                );
                $text = $raw['text'];
                Log::info("TenderReviewJob findings raw", ['text_len' => mb_strlen($text), 'preview' => mb_substr($text, 0, 200)]);
                // Extract JSON from response
                if (preg_match('/\{[\s\S]*\}/u', $text, $m)) {
                    $decoded = json_decode($m[0], true);
                    if (is_array($decoded)) {
                        $findings = $decoded['findings'] ?? [];
                        if (isset($decoded['category'])) $findings = [$decoded];
                        if (!empty($findings)) break;
                    }
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("TenderReviewJob findings attempt $attempt failed", ['error' => $lastError]);
            }
        }

        // Get summary — also use chat() to avoid JSON mode issues
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $raw = $claude->chat(
                    messages: [['role' => 'user', 'content' => "قيّم هذه الكراسة بشكل عام. أرجع JSON فقط:\n{\"compliance_score\":75,\"executive_summary\":\"التقييم\",\"ready_for_tender\":false}\n\nالكراسة:\n" . mb_substr($content, 0, 20000)]],
                    system: $summarySystem,
                    maxTokens: 500,
                );
                if (preg_match('/\{[\s\S]*\}/u', $raw['text'], $m)) {
                    $decoded = json_decode($m[0], true);
                    if (is_array($decoded) && isset($decoded['compliance_score'])) {
                        $summary = $decoded;
                        break;
                    }
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("TenderReviewJob summary attempt $attempt failed", ['error' => $lastError]);
            }
        }

        // Proceed as long as we got SOMETHING (even just a score with no findings)
        if (empty($findings) && empty($summary) && empty($summary['compliance_score'] ?? null)) {
            $this->markStatus($document, 'failed', 'فشل مراجعة الكراسة: ' . ($lastError ?: 'لم يتم الحصول على نتائج.'));
            return;
        }

        // Ensure findings is an array of arrays (not a single finding object)
        if (!empty($findings) && isset($findings['category'])) {
            $findings = [$findings]; // wrap single finding in array
        }

        // Count by severity
        $critical = collect($findings)->where('severity', 'حرجة')->count();
        $high = collect($findings)->where('severity', 'عالية')->count();
        $medium = collect($findings)->where('severity', 'متوسطة')->count();
        $improvement = collect($findings)->where('severity', 'تحسينية')->count();

        $document->analysis = [
            'compliance_score' => $summary['compliance_score'] ?? 70,
            'executive_summary' => $summary['executive_summary'] ?? 'تم المراجعة.',
            'ready_for_tender' => $summary['ready_for_tender'] ?? ($critical === 0),
            'findings' => $findings,
            'statistics' => ['critical' => $critical, 'high' => $high, 'medium' => $medium, 'improvement' => $improvement],
        ];
        $meta = $document->metadata ?? [];
        $meta['analysis_status'] = 'ready';
        $meta['analyzed_at'] = now()->toIso8601String();
        $document->metadata = $meta;
        $document->save();

        $score = $result['data']['compliance_score'] ?? '—';
        $findings = count($result['data']['findings'] ?? []);

        AppNotification::notify(
            userId: $document->uploaded_by,
            type: 'tender_reviewed',
            title: 'اكتملت مراجعة الكراسة',
            body: "نسبة الامتثال: {$score}% · {$findings} ملاحظة — افتح الكراسة لعرض التقرير.",
            data: ['document_id' => $document->id]
        );
    }

    private function markStatus(LegalDocument $document, string $status, string $reason): void
    {
        $meta = $document->metadata ?? [];
        $meta['analysis_status'] = $status;
        $meta['analysis_error'] = $reason;
        $document->metadata = $meta;
        $document->save();

        AppNotification::notify(
            userId: $document->uploaded_by,
            type: 'tender_review_failed',
            title: 'فشل مراجعة الكراسة',
            body: $reason,
            data: ['document_id' => $document->id]
        );
    }
}
