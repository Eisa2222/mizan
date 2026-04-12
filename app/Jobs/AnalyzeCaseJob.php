<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\LegalDocument;
use App\Services\ClaudeService;
use App\Services\TasbibatKnowledgeService;
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

    public function handle(ClaudeService $claude, TasbibatKnowledgeService $tasbibat): void
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

        // Retrieve similar judicial reasoning patterns for RAG context
        $tasbibatContext = $tasbibat->buildContextFor($content, topK: 5);

        $system = <<<SYSTEM
أنت محلل قضايا قانونية سعودي متخصص. حلّل القضية بعمق واستعن بالتسبيبات القضائية المرفقة لتدعيم تحليلك.

المهام:
1. لخّص القضية وحدد المحكمة والحكم.
2. حدد الأسانيد النظامية المرتبطة مع شرح وجه الاستدلال.
3. اذكر الأنظمة المطبّقة.
4. قدم توقعاً مبنياً على أنماط التسبيب المشابهة المرفقة — إذا وُجد نمط مشابه اذكره صراحة.
5. حدد عوامل النجاح وعوامل الخطر.
6. إذا وجدت سوابق قضائية من التسبيبات المرفقة تدعم أو تخالف الحكم، أشر إليها.

{$tasbibatContext}

أرجع JSON فقط بالشكل التالي (بالعربية فقط):
{"summary":"ملخص","court":"الجهة","ruling":"الحكم",
"legal_basis":[{"reference":"المرجع","explanation":"شرح","similar_pattern":"نمط تسبيب مشابه إن وُجد"}],
"applicable_laws":["نظام"],"outcome_prediction":"توقع",
"success_factors":["عامل نجاح"],"risk_factors":["عامل خطر"],
"related_tasbibat":["إشارة لتسبيب مشابه من القاعدة المعرفية إن وُجد"]}
SYSTEM;

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
