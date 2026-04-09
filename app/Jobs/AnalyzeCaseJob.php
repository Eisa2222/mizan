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
 * AnalyzeCaseJob
 * ──────────────
 * Same shape as AnalyzeContractJob but tuned for legal cases (kind=case).
 * Result: a structured JSON in legal_documents.analysis with court info,
 * legal basis, precedents, applicable laws and an outcome prediction.
 */
class AnalyzeCaseJob implements ShouldQueue
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
            $this->markStatus($document, 'failed', 'لا يوجد نص في القضية للتحليل.');
            return;
        }

        $content = mb_substr($content, 0, 80000);

        $system = 'أنت محلل قضايا قانونية. حلّل القضية وأرجع JSON فقط (بالعربية):'
            . ' {"summary":"ملخص","court":"الجهة","ruling":"الحكم",'
            . '"legal_basis":[{"reference":"المرجع","explanation":"شرح"}],'
            . '"applicable_laws":["نظام"],"outcome_prediction":"توقع",'
            . '"success_factors":["عامل نجاح"],"risk_factors":["عامل خطر"]}'
            . "\n\nأرجع JSON فقط بدون أي نص إضافي. بالعربية فقط.";

        try {
            $result = $claude->chatJson(
                messages: [['role' => 'user', 'content' => $content]],
                system: $system,
                maxTokens: 4096,
            );
        } catch (Throwable $e) {
            Log::error('AnalyzeCaseJob failed', [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);
            $this->markStatus($document, 'failed', 'فشل تحليل القضية: ' . $e->getMessage());
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
            type: 'case_analyzed',
            title: 'اكتمل تحليل القضية',
            body: 'تم تحليل القضية "' . $document->title . '". افتح الوثيقة لعرض الأساس القانوني والتوقعات.',
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
            type: 'case_analysis_failed',
            title: 'فشل تحليل القضية',
            body: $reason,
            data: ['document_id' => $document->id]
        );
    }
}
