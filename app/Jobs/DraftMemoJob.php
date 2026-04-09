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
 * DraftMemoJob
 * ────────────
 * Analyzes a draft legal memorandum (مسودة مذكرة): checks legal reasoning,
 * identifies weak arguments, suggests stronger references, assesses overall
 * persuasiveness, and proposes structural improvements.
 */
class DraftMemoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

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
            $this->markStatus($document, 'failed', 'لا يوجد نص في المذكرة للتحليل.');
            return;
        }

        $content = mb_substr($content, 0, 80000);

        $system = 'أنت خبير مراجعة مذكرات قانونية. حلّل المذكرة وأرجع JSON فقط (بالعربية):'
            . ' {"summary":"ملخص","memo_type":"نوع المذكرة",'
            . '"legal_arguments":[{"argument":"الحجة","strength":"قوية أو متوسطة أو ضعيفة","note":"ملاحظة"}],'
            . '"weak_points":[{"point":"نقطة ضعف","suggestion":"اقتراح"}],'
            . '"missing_references":["مرجع مقترح"],'
            . '"overall_assessment":"جيدة أو تحتاج تحسين","persuasiveness_score":"7",'
            . '"key_recommendations":["توصية"]}'
            . "\n\nأرجع JSON فقط بدون أي نص إضافي. بالعربية فقط.";

        try {
            $result = $claude->chatJson(
                messages: [['role' => 'user', 'content' => $content]],
                system: $system,
                maxTokens: 4096,
            );
        } catch (Throwable $e) {
            Log::error('DraftMemoJob failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            $this->markStatus($document, 'failed', 'فشل تحليل المذكرة: ' . $e->getMessage());
            return;
        }

        $document->analysis = $result['data'];
        $meta = $document->metadata ?? [];
        $meta['analysis_status'] = 'ready';
        $meta['analyzed_at'] = now()->toIso8601String();
        $document->metadata = $meta;
        $document->save();

        AppNotification::notify(
            userId: $document->uploaded_by,
            type: 'memo_analyzed',
            title: 'اكتمل تحليل المذكرة',
            body: 'تم تحليل المسودة "' . $document->title . '". افتح الوثيقة لعرض التوصيات.',
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
            type: 'memo_analysis_failed',
            title: 'فشل تحليل المذكرة',
            body: $reason,
            data: ['document_id' => $document->id]
        );
    }
}
