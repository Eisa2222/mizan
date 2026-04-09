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

        $system = 'أنت مراجع كراسات منافسات حكومية متخصص في نظام المنافسات والمشتريات الحكومية السعودي ولائحته التنفيذية.'
            . "\n\nحلّل كراسة الشروط والمواصفات التالية وأرجع JSON فقط بالعربية بهذا الشكل:"
            . "\n" . '{"compliance_score":85,'
            . '"executive_summary":"ملخص تنفيذي: هل الكراسة جاهزة للطرح أم تحتاج مراجعة",'
            . '"ready_for_tender":true,'
            . '"needs_legal_review":false,'
            . '"critical_items":["بند حرج يجب تعديله قبل الطرح"],'
            . '"findings":['
            .   '{"category":"نظامي","severity":"حرجة","title":"عنوان الملاحظة","current_text":"النص الحالي من الكراسة","issue":"شرح المشكلة بالتفصيل","legal_reference":"المادة XX من نظام المنافسات","recommendation":"التوصية أو الصياغة المقترحة"},'
            .   '{"category":"شكلي","severity":"متوسطة","title":"عنوان","current_text":"النص","issue":"المشكلة","legal_reference":"","recommendation":"التوصية"},'
            .   '{"category":"موضوعي","severity":"عالية","title":"عنوان","current_text":"النص","issue":"المشكلة","legal_reference":"","recommendation":"التوصية"},'
            .   '{"category":"عدالة","severity":"تحسينية","title":"عنوان","current_text":"النص","issue":"المشكلة","legal_reference":"","recommendation":"التوصية"}'
            . '],'
            . '"statistics":{"critical":1,"high":2,"medium":3,"improvement":1},'
            . '"missing_sections":["قسم ناقص يجب إضافته"],'
            . '"restricted_conditions":["شرط مقيّد قد يحد من المنافسة"]'
            . '}'
            . "\n\nتعليمات مهمة:"
            . "\n1. افحص كل بند في الكراسة وقارنه بنظام المنافسات والمشتريات الحكومية"
            . "\n2. صنّف كل ملاحظة: نظامي أو شكلي أو موضوعي أو عدالة"
            . "\n3. حدد الخطورة: حرجة أو عالية أو متوسطة أو تحسينية"
            . "\n4. اكتب النص الحالي من الكراسة في current_text"
            . "\n5. اذكر المرجع النظامي (رقم المادة) في legal_reference"
            . "\n6. اكتب التوصية أو الصياغة المقترحة في recommendation"
            . "\n7. compliance_score من 0 إلى 100"
            . "\n8. JSON فقط بالعربية. لا تكتب أي نص خارج JSON";

        try {
            $result = $claude->chatJson(
                messages: [['role' => 'user', 'content' => "راجع كراسة الشروط والمواصفات التالية:\n\n" . $content]],
                system: $system,
                maxTokens: 6000,
            );
        } catch (Throwable $e) {
            Log::error('TenderReviewJob failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);
            $this->markStatus($document, 'failed', 'فشل مراجعة الكراسة: ' . $e->getMessage());
            return;
        }

        $document->analysis = $result['data'];
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
