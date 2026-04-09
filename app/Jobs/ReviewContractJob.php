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
 * ReviewContractJob
 * ─────────────────
 * Deep contract review: compares clauses against Saudi legal standards,
 * flags missing protections, checks compliance with labor/commercial law,
 * and suggests specific amendments. More thorough than AnalyzeContractJob.
 */
class ReviewContractJob implements ShouldQueue
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
            $this->markStatus($document, 'failed', 'لا يوجد نص في العقد للمراجعة.');
            return;
        }

        $content = mb_substr($content, 0, 80000);

        $system = 'أنت مراجع عقود قانوني سعودي. أرجع JSON فقط بالعربية بهذا الشكل:'
            . ' {"summary":"ملخص تفصيلي 3-5 جمل","risks":[{"severity":"high","description":"وصف تفصيلي للمخاطرة","clause":"نص البند المعني من العقد","mitigation":"الحل المقترح بالتفصيل"}],'
            . '"recommended_amendments":[{"current":"النص الحالي كاملاً من العقد","suggested":"النص المقترح بعد التعديل كاملاً","reason":"سبب التعديل"}],'
            . '"missing_protections":["وصف تفصيلي للبند الناقص"],"overall_rating":"جيد","overall_notes":"ملاحظات تفصيلية"}'
            . "\n\nتعليمات: 1) اكتب تفاصيل كاملة في كل حقل 2) في recommended_amendments اكتب النص الأصلي من العقد في current والنص المعدّل في suggested 3) في risks.mitigation اكتب الحل كاملاً 4) JSON فقط بالعربية";

        try {
            $result = $claude->chatJson(
                messages: [['role' => 'user', 'content' => "راجع هذا العقد بالتفصيل:\n\n" . $content]],
                system: $system,
                maxTokens: 6000,
            );
        } catch (Throwable $e) {
            Log::error('ReviewContractJob failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            $this->markStatus($document, 'failed', 'فشل مراجعة العقد: ' . $e->getMessage());
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
            type: 'contract_reviewed',
            title: 'اكتملت مراجعة العقد',
            body: 'تم مراجعة العقد "' . $document->title . '". افتح الوثيقة لعرض نتائج المراجعة.',
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
            type: 'contract_review_failed',
            title: 'فشل مراجعة العقد',
            body: $reason,
            data: ['document_id' => $document->id]
        );
    }
}
