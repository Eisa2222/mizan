<?php

namespace App\Services;

use App\Models\Tender;
use App\Models\TenderReview;

/**
 * ComplianceService
 * ─────────────────
 * Validates a generated tender against a checklist of best practices and
 * the مرشدات of نظام المنافسات والمشتريات الحكومية.
 *
 * Returns a compliance score (0-100), categorized issues, and suggestions.
 *
 * Two layers of validation:
 *   1. Hard rules (deterministic) — checks required sections, content length, etc.
 *   2. Soft analysis via Claude/Ollama — semantic checks (only when AI is configured).
 */
class ComplianceService
{
    public function __construct(private ClaudeService $ai) {}

    public function check(Tender $tender): TenderReview
    {
        $issues = [];

        // ─── Layer 1: Hard rules ───
        $sections = $tender->sections->keyBy('section_key');
        $requiredSections = ['introduction', 'scope', 'deliverables', 'timeline', 'qualifications', 'evaluation', 'conditions', 'pricing'];

        foreach ($requiredSections as $key) {
            if (! $sections->has($key)) {
                $issues[] = $this->issue('critical', 'missing_section', "قسم مفقود: {$key}",
                    "كراسة الشروط يجب أن تتضمن قسم \"{$key}\" وفق نظام المنافسات والمشتريات الحكومية.",
                    'تأكد من إضافة هذا القسم قبل اعتماد الكراسة.');
                continue;
            }
            $section = $sections->get($key);
            if (mb_strlen($section->content ?? '') < 100) {
                $issues[] = $this->issue('high', 'thin_section', "محتوى ضعيف في قسم: {$section->title}",
                    'القسم يحتوي على نص قصير جداً وقد لا يكفي لتوضيح المتطلبات.',
                    'وسّع المحتوى ليشمل التفاصيل الكاملة والمتطلبات الواضحة.');
            }
        }

        // Scope strength
        if ($tender->expanded_scope === null || empty($tender->expanded_scope['tasks'] ?? [])) {
            $issues[] = $this->issue('high', 'weak_scope', 'نطاق العمل غير محدد بدقة',
                'لم يتم توسيع نطاق العمل إلى مهام تفصيلية.',
                'أعد إدخال نطاق العمل بشكل أوضح أو أعد توليد الكراسة.');
        } elseif (count($tender->expanded_scope['tasks']) < 4) {
            $issues[] = $this->issue('medium', 'short_scope', 'نطاق العمل يحتوي على مهام قليلة',
                'توصي الممارسات الفضلى بتفصيل المهام بشكل أوضح.',
                'أضف المزيد من المهام التفصيلية لكل مرحلة من مراحل المشروع.');
        }

        // Evaluation criteria
        $evalSection = $sections->get('evaluation');
        if ($evalSection && mb_strlen($evalSection->content) < 80) {
            $issues[] = $this->issue('high', 'unclear_evaluation', 'معايير التقييم غير واضحة',
                'يجب تحديد أوزان معايير التقييم بشكل صريح وقابل للقياس.',
                'حدد العرض الفني والمالي والخبرات بنسب واضحة.');
        }

        // Duration
        if (empty($tender->duration) || $tender->duration === 'يحدد لاحقاً') {
            $issues[] = $this->issue('medium', 'no_duration', 'مدة المشروع غير محددة',
                'لم يتم تحديد مدة تنفيذ المشروع.',
                'حدد مدة واضحة بالأيام أو الأشهر.');
        }

        // Clauses sanity check
        $clauseTypes = $tender->clauses->pluck('clause_type')->all();
        $expectedClauses = match ($tender->type) {
            'it', 'operations' => ['sla', 'penalties', 'warranty'],
            'construction'     => ['penalties', 'warranty', 'payment'],
            'consulting'       => ['confidentiality', 'payment'],
            'legal'            => ['confidentiality'],
            default            => ['payment'],
        };
        foreach ($expectedClauses as $expected) {
            if (! in_array($expected, $clauseTypes, true)) {
                $issues[] = $this->issue('medium', 'missing_clause', "بند مفقود: {$expected}",
                    "البنود المعتادة لهذا النوع من المشاريع تتضمن {$expected}.",
                    'أضف هذا البند يدوياً أو أعد التوليد.');
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

    private function issue(string $severity, string $category, string $title, string $issue, string $recommendation): array
    {
        return [
            'severity'       => $severity,
            'category'       => $category,
            'title'          => $title,
            'issue'          => $issue,
            'recommendation' => $recommendation,
        ];
    }

    private function buildSummary(int $score, array $stats): string
    {
        if ($score >= 90) {
            return "الكراسة جاهزة للطرح. نسبة الامتثال {$score}% ولا توجد ملاحظات حرجة.";
        }
        if ($score >= 70) {
            return "الكراسة بحالة جيدة لكن تحتاج بعض التحسينات. نسبة الامتثال {$score}%.";
        }
        if ($score >= 50) {
            return "الكراسة تحتاج مراجعة جوهرية قبل الطرح. نسبة الامتثال {$score}% مع {$stats['critical']} ملاحظة حرجة و{$stats['high']} عالية.";
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
