<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\LegalDocument;
use App\Services\ClaudeService;
use App\Services\TasbibatKnowledgeService;
use App\Services\TrainingCorpusService;
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

    public function handle(ClaudeService $claude, TasbibatKnowledgeService $tasbibat, TrainingCorpusService $corpus): void
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

        // Retrieve tasbibat relevant to contract disputes for RAG context
        $tasbibatContext = $tasbibat->buildContextFor($content, courtType: 'تجاري', topK: 4);

        // Reference corpus: Saudi regulation texts + contract templates
        $corpusContext = $corpus->buildContextFor($content, kinds: ['document', 'contract'], topK: 3);

        $system = <<<SYSTEM
أنت مراجع عقود قانوني سعودي متخصص. راجع العقد بعمق واستعن بالتسبيبات القضائية والمراجع المرفقة لتحديد المخاطر القانونية بناءً على الأنظمة والنماذج المعتمدة.

المهام:
1. لخّص العقد (3-5 جمل).
2. حدد المخاطر القانونية مع درجة الخطورة (critical/high/medium/low) وبيّن البند المعني والحل.
3. إذا وجدت حكماً قضائياً من التسبيبات المرفقة يتعلق بنزاع مشابه لبند في العقد، أشر إليه.
4. اقترح تعديلات محددة (النص الحالي → النص المقترح) مع السبب.
5. حدد البنود الناقصة.

{$tasbibatContext}

{$corpusContext}

أرجع JSON فقط بالعربية بهذا الشكل:
{"summary":"ملخص تفصيلي 3-5 جمل","risks":[{"severity":"high","description":"وصف تفصيلي للمخاطرة","clause":"نص البند المعني من العقد — اقتباس حرفي","source_quote":"اقتباس حرفي كامل من نص العقد يوضح موضع الخطر","mitigation":"الحل المقترح بالتفصيل","judicial_precedent":"إشارة لتسبيب قضائي مشابه إن وُجد"}],
"compliance_issues":[{"law":"اسم النظام","article":"رقم المادة","issue":"وصف المخالفة","source_quote":"اقتباس حرفي من العقد للنص المخالف","recommendation":"التوصية"}],
"recommended_amendments":[{"current":"النص الحالي كاملاً — اقتباس حرفي من العقد","suggested":"النص المقترح بعد التعديل كاملاً","reason":"سبب التعديل"}],
"missing_protections":[{"issue":"وصف تفصيلي للبند الناقص","related_clause":"اقتباس من البند الحالي القريب الذي كان يفترض إضافة الحماية عنده (إن وُجد)"}],"overall_rating":"جيد","overall_notes":"ملاحظات تفصيلية"}

تعليمات:
1) اكتب تفاصيل كاملة في كل حقل
2) في recommended_amendments اكتب النص الأصلي من العقد في current والنص المعدّل في suggested
3) في risks.mitigation اكتب الحل كاملاً
4) **source_quote حقل إلزامي** في كل risk و compliance_issue — يجب أن يكون اقتباساً حرفياً (verbatim) من نص العقد المرفق، ليطابق ما كتبه الأطراف بالضبط، حتى يتمكن المراجع من تحديد موضع الملاحظة
5) JSON فقط بالعربية
SYSTEM;

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
