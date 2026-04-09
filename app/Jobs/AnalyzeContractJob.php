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
 * AnalyzeContractJob
 * ──────────────────
 * Runs a structured legal-risk analysis on a document with kind=contract.
 * The result is stored as JSON in legal_documents.analysis and surfaced
 * in a dedicated tab on the document show page.
 *
 * Dispatched from DocumentController::store() when:
 *   - kind === 'contract'
 *   - extracted content is present (synchronous extractor or OCR job)
 *
 * The job is no-op safe if Claude isn't configured — it records a friendly
 * status in metadata and notifies the uploader.
 */
class AnalyzeContractJob implements ShouldQueue
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
            $this->markStatus($document, 'unavailable', 'ميزة AI غير متاحة. لم يتم إجراء التحليل.');
            return;
        }

        $content = trim((string) $document->content);
        if ($content === '') {
            $this->markStatus($document, 'failed', 'لا يوجد نص في العقد للتحليل.');
            return;
        }

        // Truncate to keep cost predictable; legal contracts > 80K chars are rare
        $content = mb_substr($content, 0, 80000);

        $system = 'أنت محلل عقود قانوني. حلّل العقد وأرجع JSON فقط (بالعربية):'
            . ' {"summary":"ملخص","parties":["طرف1","طرف2"],'
            . '"risks":[{"clause":"البند","severity":"high","explanation":"شرح","suggested_change":"اقتراح"}],'
            . '"obligations":[{"party":"الطرف","obligation":"الالتزام"}],'
            . '"missing_clauses":["بند مفقود"]}'
            . "\n\nأرجع JSON فقط بدون أي نص إضافي. severity: low أو medium أو high. بالعربية فقط.";

        try {
            $result = $claude->chatJson(
                messages: [['role' => 'user', 'content' => $content]],
                system: $system,
                maxTokens: 4096,
            );
        } catch (Throwable $e) {
            Log::error('AnalyzeContractJob failed', [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);
            $this->markStatus($document, 'failed', 'فشل تحليل العقد: ' . $e->getMessage());
            return;
        }

        $document->analysis = $result['data'];
        $meta = $document->metadata ?? [];
        $meta['analysis_status'] = 'ready';
        $meta['analyzed_at']     = now()->toIso8601String();
        $document->metadata = $meta;
        $document->save();

        AppNotification::notify(
            userId: $document->uploaded_by,
            type: 'contract_analyzed',
            title: 'اكتمل تحليل العقد',
            body: 'تم تحليل العقد "' . $document->title . '". افتح الوثيقة لعرض المخاطر والاقتراحات.',
            data: ['document_id' => $document->id]
        );
    }

    private function markStatus(LegalDocument $document, string $status, string $reason): void
    {
        $meta = $document->metadata ?? [];
        $meta['analysis_status'] = $status;
        $meta['analysis_error']  = $reason;
        $document->metadata = $meta;
        $document->save();

        AppNotification::notify(
            userId: $document->uploaded_by,
            type: 'contract_analysis_failed',
            title: 'فشل تحليل العقد',
            body: $reason,
            data: ['document_id' => $document->id]
        );
    }
}
