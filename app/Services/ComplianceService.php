<?php

namespace App\Services;

use App\Models\Tender;
use App\Models\TenderReview;

/**
 * ComplianceService
 * ─────────────────
 * Validates a generated tender against:
 *
 *   1. Hard rules (deterministic) — required sections, length, structure
 *   2. The seeded GPC knowledge base (نظام م/128 + لائحة 1242 + الأدلة)
 *      via GpcKnowledgeService — every issue links to the real article
 *      that backs it.
 *
 * Returns a compliance score (0-100), categorized issues with citations,
 * and actionable recommendations.
 */
class ComplianceService
{
    public function __construct(
        private ClaudeService $ai,
        private GpcKnowledgeService $knowledge,
    ) {}

    public function check(Tender $tender): TenderReview
    {
        // Map each finding category to a specific article via direct keyword
        // lookup. This guarantees citations even for empty/weak tenders.
        $citationByTopic = [
            'missing_section'    => $this->lookup('كراسة'),
            'thin_section'       => $this->lookup('كراسة'),
            'weak_scope'         => $this->lookup('نطاق العمل'),
            'short_scope'        => $this->lookup('نطاق العمل'),
            'unclear_evaluation' => $this->lookup('تقييم'),
            'no_duration'        => $this->lookup('جدول'),
            'missing_clause'     => $this->lookup('ضمانات'),
        ];
        $issues = [];

        // ─── Layer 1: Hard rules ───
        $sections = $tender->sections->keyBy('section_key');
        $requiredSections = ['introduction', 'scope', 'deliverables', 'methodology', 'timeline', 'qualifications', 'evaluation', 'conditions', 'boq', 'pricing'];

        foreach ($requiredSections as $key) {
            if (! $sections->has($key)) {
                $issues[] = $this->issue('critical', 'missing_section', "قسم مفقود: {$key}",
                    "كراسة الشروط يجب أن تتضمن قسم \"{$key}\" وفق نظام المنافسات والمشتريات الحكومية ولائحته التنفيذية.",
                    'تأكد من إضافة هذا القسم قبل اعتماد الكراسة.',
                    $citationByTopic['missing_section']);
                continue;
            }
            $section = $sections->get($key);
            if (mb_strlen($section->content ?? '') < 100) {
                $issues[] = $this->issue('high', 'thin_section', "محتوى ضعيف في قسم: {$section->title}",
                    'القسم يحتوي على نص قصير جداً وقد لا يكفي لتوضيح المتطلبات بشكل لا يحتمل اللبس.',
                    'وسّع المحتوى ليشمل التفاصيل الكاملة والمتطلبات الواضحة.',
                    $citationByTopic['thin_section']);
            }
        }

        // Scope strength
        if ($tender->expanded_scope === null || empty($tender->expanded_scope['tasks'] ?? [])) {
            $issues[] = $this->issue('high', 'weak_scope', 'نطاق العمل غير محدد بدقة',
                'لم يتم توسيع نطاق العمل إلى مهام تفصيلية. اللائحة التنفيذية تشترط تحديد نطاق العمل بدقة ووضوح.',
                'أعد إدخال نطاق العمل بشكل أوضح أو أعد توليد الكراسة.',
                $citationByTopic['weak_scope']);
        } elseif (count($tender->expanded_scope['tasks']) < 4) {
            $issues[] = $this->issue('medium', 'short_scope', 'نطاق العمل يحتوي على مهام قليلة',
                'توصي الأدلة الإجرائية بتفصيل المهام بشكل أوضح لتجنب الخلافات اللاحقة.',
                'أضف المزيد من المهام التفصيلية لكل مرحلة من مراحل المشروع.',
                $citationByTopic['short_scope']);
        }

        // Evaluation criteria
        $evalSection = $sections->get('evaluation');
        if ($evalSection && mb_strlen($evalSection->content) < 80) {
            $issues[] = $this->issue('high', 'unclear_evaluation', 'معايير التقييم غير واضحة',
                'يجب تحديد أوزان معايير التقييم بشكل صريح وقابل للقياس وفق اللائحة التنفيذية.',
                'حدد العرض الفني والمالي والخبرات بنسب واضحة (مثلاً: فني 60%، مالي 30%، خبرات 10%).',
                $citationByTopic['unclear_evaluation']);
        }

        // Duration — mandatory per نظام المنافسات
        if (empty($tender->duration) || $tender->duration === 'يحدد لاحقاً') {
            $issues[] = $this->issue('high', 'no_duration', 'مدة المشروع غير محددة',
                'لم يتم تحديد مدة تنفيذ المشروع، وهو شرط أساسي في وثائق المنافسة. اللائحة التنفيذية تشترط تحديد المدة صراحةً.',
                'حدد مدة واضحة بالأيام أو الأشهر قبل طرح الكراسة.',
                $citationByTopic['no_duration']);
        }

        // Clauses sanity check
        $clauseTypes = $tender->clauses->pluck('clause_type')->all();
        $expectedClauses = match ($tender->type) {
            'it', 'it_install'                                => ['sla', 'penalties', 'warranty'],
            'it_supply'                                       => ['penalties', 'warranty', 'payment'],
            'it_consulting'                                   => ['sla', 'confidentiality', 'payment'],
            'operations', 'cleaning', 'security'              => ['sla', 'penalties', 'warranty'],
            'construction', 'supply', 'medical_supply'        => ['penalties', 'warranty', 'payment'],
            'consulting', 'training'                          => ['confidentiality', 'payment'],
            'engineering_design', 'engineering_super'          => ['confidentiality', 'payment'],
            'framework'                                       => ['sla', 'penalties', 'payment'],
            'catering', 'transport'                           => ['sla', 'penalties', 'payment'],
            'legal'                                           => ['confidentiality'],
            default                                           => ['payment'],
        };
        foreach ($expectedClauses as $expected) {
            if (! in_array($expected, $clauseTypes, true)) {
                $issues[] = $this->issue('medium', 'missing_clause', "بند مفقود: {$expected}",
                    "البنود المعتادة لهذا النوع من المشاريع تتضمن {$expected} وفق ممارسات النظام.",
                    'أضف هذا البند يدوياً أو أعد التوليد.',
                    $citationByTopic['missing_clause']);
            }
        }

        // ─── Calculate score ───
        $statistics = [
            'critical'    => collect($issues)->where('severity', 'critical')->count(),
            'high'        => collect($issues)->where('severity', 'high')->count(),
            'medium'      => collect($issues)->where('severity', 'medium')->count(),
            'improvement' => collect($issues)->where('severity', 'improvement')->count(),
        ];

        $score = $this->computeScore($statistics);

        $summary = $this->buildSummary($score, $statistics);

        // ─── Persist review ───
        return TenderReview::create([
            'tender_id'        => $tender->id,
            'compliance_score' => $score,
            'issues'           => $issues,
            'recommendations'  => $this->buildRecommendations($issues),
            'statistics'       => $statistics,
            'summary'          => $summary,
        ]);
    }

    private function computeScore(array $stats): int
    {
        // Start at 100, deduct based on severity weights
        $score = 100
            - ($stats['critical'] * 25)
            - ($stats['high'] * 10)
            - ($stats['medium'] * 4)
            - ($stats['improvement'] * 1);
        return max(0, min(100, $score));
    }

    private function issue(string $severity, string $category, string $title, string $issue, string $recommendation, ?array $citation = null): array
    {
        return [
            'severity'        => $severity,
            'category'        => $category,
            'title'           => $title,
            'issue'           => $issue,
            'recommendation'  => $recommendation,
            'legal_reference' => $citation['label'] ?? null,
            'reference_source' => $citation['source'] ?? null,
            'violation_type'  => in_array($severity, ['critical', 'high'], true) ? 'مخالفة مؤكدة' : 'مؤشر خطر',
        ];
    }

    /**
     * Find the first GPC article whose keywords or topic match the search term.
     * Returns the article's label and source for use as a citation.
     */
    private function lookup(string $term): ?array
    {
        $article = \App\Models\GpcArticle::where('keywords', 'like', "%{$term}%")
            ->orWhere('topic', 'like', "%{$term}%")
            ->orWhere('content', 'like', "%{$term}%")
            ->orderBy('source') // prefer regulation/system before guides
            ->first();

        if (! $article) return null;

        return [
            'label'  => $article->article_label,
            'source' => $article->source_label ?? $article->source,
        ];
    }

    private function buildSummary(int $score, array $stats): string
    {
        if ($score >= 90 && $stats['critical'] === 0 && $stats['high'] === 0) {
            return "الكراسة جاهزة للطرح. نسبة الامتثال {$score}% ولا توجد ملاحظات حرجة أو عالية.";
        }
        if ($stats['critical'] > 0) {
            return "الكراسة تحتاج مراجعة جوهرية. نسبة الامتثال {$score}% مع {$stats['critical']} ملاحظة حرجة يجب معالجتها قبل الطرح.";
        }
        if ($stats['high'] > 0) {
            return "الكراسة تحتاج تعديلات قبل الطرح. نسبة الامتثال {$score}% مع {$stats['high']} ملاحظة عالية الأهمية.";
        }
        if ($score >= 70) {
            return "الكراسة بحالة جيدة لكن تحتاج بعض التحسينات الطفيفة. نسبة الامتثال {$score}%.";
        }
        if ($score >= 50) {
            return "الكراسة تحتاج مراجعة جوهرية قبل الطرح. نسبة الامتثال {$score}%.";
        }
        return "الكراسة لا تستوفي الحد الأدنى من المتطلبات. لا يُنصح بطرحها قبل المعالجة.";
    }

    private function buildRecommendations(array $issues): array
    {
        return collect($issues)
            ->where('severity', '!=', 'improvement')
            ->take(5)
            ->map(fn ($i) => $i['recommendation'])
            ->values()
            ->all();
    }
}
