<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Services\ClaudeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('documents:distill-tenders {--batch=krasat_templates_2026_04 : import_batch marker} {--force : Re-distill} {--limit= : Max docs} {--id= : Single doc}')]
#[Description('Distill tender booklet templates into structured knowledge (sections, clauses, evaluation criteria, patterns) for AI generation + review')]
class DistillTenderTemplates extends Command
{
    public function handle(ClaudeService $ai): int
    {
        if (! $ai->isConfigured()) {
            $this->error('AI غير مُكوَّن.');
            return self::FAILURE;
        }

        $query = LegalDocument::query()
            ->where('kind', LegalDocument::KIND_DOCUMENT)
            ->whereJsonContains('metadata->import_batch', $this->option('batch'));

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        if (! $this->option('force')) {
            $query->where(function ($w) {
                $w->whereNull('analysis')->orWhereJsonLength('analysis', 0);
            });
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $docs = $query->orderBy('id')->get();
        $total = $docs->count();

        if ($total === 0) {
            $this->info('لا توجد مستندات للمعالجة.');
            return self::SUCCESS;
        }

        $this->info("📋 سيتم تقطير {$total} نموذج كراسة.");
        $stats = ['ok' => 0, 'failed' => 0, 'empty' => 0];
        $start = microtime(true);

        foreach ($docs as $i => $doc) {
            $idx = $i + 1;
            $content = trim((string) $doc->content);
            if ($content === '') {
                $this->line("  [{$idx}/{$total}] ⊘ #{$doc->id} — فارغ");
                $stats['empty']++;
                continue;
            }

            $truncated = mb_substr($content, 0, 6000);
            $this->line("  [{$idx}/{$total}] ⌛ #{$doc->id} — {$doc->title}");
            $t = microtime(true);

            try {
                $result = $ai->chatJson(
                    messages: [['role' => 'user', 'content' => $truncated]],
                    system: $this->tenderPrompt($doc),
                    maxTokens: 2048,
                );

                $data = $result['data'];
                if (! is_array($data) || ! isset($data['template_type'])) {
                    throw new \RuntimeException('رد AI بدون "template_type"');
                }

                $analysis = [
                    'template_type'        => (string) ($data['template_type'] ?? ''),
                    'summary'              => (string) ($data['summary'] ?? ''),
                    'required_sections'    => array_values((array) ($data['required_sections'] ?? [])),
                    'standard_clauses'     => array_values((array) ($data['standard_clauses'] ?? [])),
                    'evaluation_criteria'  => array_values((array) ($data['evaluation_criteria'] ?? [])),
                    'timeline_patterns'    => array_values((array) ($data['timeline_patterns'] ?? [])),
                    'penalty_patterns'     => array_values((array) ($data['penalty_patterns'] ?? [])),
                    'sla_patterns'         => array_values((array) ($data['sla_patterns'] ?? [])),
                    'key_phrases'          => array_values((array) ($data['key_phrases'] ?? [])),
                    'compliance_notes'     => array_values((array) ($data['compliance_notes'] ?? [])),
                    'distilled_at'         => now()->toIso8601String(),
                    'distill_model'        => config('services.ai.ollama_model', 'unknown'),
                ];

                $doc->analysis = $analysis;
                $meta = $doc->metadata ?? [];
                $meta['distill_status'] = 'ready';
                $meta['distilled_at'] = now()->toIso8601String();
                $doc->metadata = $meta;
                $doc->save();

                $sects = count($analysis['required_sections']);
                $elapsed = round(microtime(true) - $t, 1);
                $this->line("     ✓ {$sects} قسم · {$elapsed}ث");
                $stats['ok']++;
            } catch (Throwable $e) {
                $elapsed = round(microtime(true) - $t, 1);
                $this->error("     ✗ فشل ({$elapsed}ث): " . mb_substr($e->getMessage(), 0, 200));
                $stats['failed']++;
                $meta = $doc->metadata ?? [];
                $meta['distill_status'] = 'failed';
                $doc->metadata = $meta;
                $doc->save();
            }
        }

        $totalMin = round((microtime(true) - $start) / 60, 1);
        $this->newLine();
        $this->info("✓ تم: {$stats['ok']} | ✗ فشل: {$stats['failed']} | ⊘ فارغ: {$stats['empty']} | ⏱ {$totalMin} دقيقة");

        return self::SUCCESS;
    }

    private function tenderPrompt(LegalDocument $doc): string
    {
        return <<<'PROMPT'
أنت خبير في إعداد كراسات الشروط والمواصفات للمنافسات الحكومية السعودية وفق نظام المنافسات والمشتريات الحكومية (م/128) ولائحته التنفيذية (1242).

مهمتك: تحليل نموذج كراسة الشروط واستخلاص هيكلها وأنماطها لاستخدامها لاحقاً في:
1. **توليد كراسات جديدة** مبنية على نفس الهيكل والبنود
2. **مراجعة كراسات** مقدمة لضمان اكتمالها وامتثالها

استخرج بدقة:

1. **template_type**: نوع الكراسة (مثال: "إنشاءات عامة"، "توريد عام"، "خدمات استشارية"، "تقنية معلومات"، "تشغيل وصيانة").
2. **summary**: ملخص 2-3 جمل يوضح ما تغطيه الكراسة والغرض منها.
3. **required_sections**: قائمة بأقسام الكراسة المطلوبة بترتيبها الصحيح. لكل قسم: {"title": "عنوان القسم", "purpose": "الغرض منه في جملة", "is_mandatory": true/false}.
4. **standard_clauses**: البنود القياسية الموجودة في الكراسة. لكل بند: {"clause_title": "عنوان البند", "clause_type": "penalties|warranty|sla|payment|confidentiality|insurance|termination|dispute|other", "typical_content": "ملخص محتوى البند المعتاد"}.
5. **evaluation_criteria**: معايير التقييم إن وُجدت. لكل معيار: {"criterion": "اسم المعيار", "weight_range": "النسبة المعتادة مثلاً 30-40%", "sub_criteria": ["معيار فرعي"]}.
6. **timeline_patterns**: أنماط الجداول الزمنية والمدد (مثال: "مدة التنفيذ 12 شهراً"، "ضمان 24 شهراً").
7. **penalty_patterns**: أنماط الغرامات والجزاءات (مثال: "غرامة تأخير 1% أسبوعياً بحد أقصى 10%").
8. **sla_patterns**: مستويات الخدمة المطلوبة إن وُجدت (مثال: "نسبة التشغيل 99.5%").
9. **key_phrases**: 8-15 عبارة مفتاحية من الكراسة تُفيد البحث والمطابقة.
10. **compliance_notes**: ملاحظات امتثال مهمة (مثال: "يشترط ضمان بنكي ابتدائي 2%").

قواعد:
- استخرج فقط ما هو موجود فعلياً في النص. لا تخترع بنوداً غير موجودة.
- اكتب كل شيء بالعربية فقط.
- أرجع JSON صالح فقط بدون أي نص إضافي.
PROMPT;
    }
}
