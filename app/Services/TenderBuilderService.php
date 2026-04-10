<?php

namespace App\Services;

use App\Models\Tender;
use App\Models\TenderClause;
use App\Models\TenderSection;
use Illuminate\Support\Facades\DB;

/**
 * TenderBuilderService
 * ────────────────────
 * Orchestrates the full tender generation pipeline:
 *
 *   1. Run ScopeAnalyzerService to expand the raw scope into tasks.
 *   2. Pick a template based on detected/selected project type.
 *   3. Build standard clauses via ClauseService.
 *   4. Render template sections with all variables interpolated.
 *   5. Persist sections + clauses in DB.
 *
 * Returns the populated Tender model with sections and clauses ready.
 */
class TenderBuilderService
{
    public function __construct(
        private ScopeAnalyzerService $scopeAnalyzer,
        private TemplateEngine $templates,
        private ClauseService $clauses,
    ) {}

    /**
     * Build (or rebuild) all sections and clauses for a tender.
     * Idempotent — wipes existing sections/clauses first.
     */
    public function build(Tender $tender): Tender
    {
        $tender->update(['status' => 'generating']);

        // 1. Run scope analyzer to expand the raw input
        $analysis = $this->scopeAnalyzer->analyze(
            rawScope: $tender->scope_input ?? $tender->description ?? $tender->title,
            projectName: $tender->title,
            hintType: $tender->type,
        );

        // 2. Use detected type if user didn't pick one or AI suggests differently
        $type = $tender->type ?: ($analysis['detected_type'] ?? 'it');
        if (! array_key_exists($type, $this->templates->availableTypes())) {
            $type = 'it';
        }

        // 3. Persist expanded scope on the tender
        $tender->update([
            'type' => $type,
            'expanded_scope' => $analysis,
        ]);

        // 4. Build clauses for this type
        $clauseRecords = $this->clauses->buildClauses($type);

        // 5. Build template context — variables that get interpolated
        $context = [
            'title'              => $tender->title,
            'description'        => $tender->description ?: '',
            'org'                => $tender->organization?->name ?? 'الجهة الحكومية',
            'date'               => now()->locale('ar')->isoFormat('D MMMM YYYY'),
            'type_label'         => Tender::TYPES[$type] ?? '',
            'duration'           => $tender->duration ?: 'يحدد لاحقاً',
            'tasks_list'         => $analysis['tasks'] ?? [],
            'deliverables_list'  => $this->mergeDeliverables($tender, $analysis),
            'evaluation_list'    => $this->formatEvaluationCriteria($tender->evaluation_criteria),
            'clauses_block'      => $this->clauses->renderAsBlock($clauseRecords),
            'special_conditions' => $this->formatSpecialConditions($tender->special_conditions),
        ];

        // 6. Render template into sections
        $sections = $this->templates->render($type, $context);

        // 7. Persist sections + clauses (replace existing)
        DB::transaction(function () use ($tender, $sections, $clauseRecords) {
            $tender->sections()->delete();
            foreach ($sections as $s) {
                $tender->sections()->create([
                    'section_key' => $s['key'],
                    'title'       => $s['title'],
                    'content'     => $s['content'],
                    'order'       => $s['order'],
                    'is_edited'   => false,
                ]);
            }
            $tender->clauses()->delete();
            foreach ($clauseRecords as $c) {
                $tender->clauses()->create($c);
            }
            $tender->update(['status' => 'ready']);
        });

        return $tender->fresh(['sections', 'clauses']);
    }

    /** Merge user-provided deliverables with AI-extracted ones (deduped). */
    private function mergeDeliverables(Tender $tender, ?array $analysis): array
    {
        $merged = collect($tender->deliverables ?? [])
            ->merge($analysis['deliverables'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();
        return $merged ?: ['يحدد المخرجات لاحقاً عند إعداد العقد التفصيلي.'];
    }

    /**
     * Evaluation criteria comes in as either an array of strings or
     * an array of {criterion, weight} objects. Render either shape
     * as bullet text.
     */
    private function formatEvaluationCriteria(?array $criteria): array
    {
        if (empty($criteria)) {
            return [
                'العرض الفني (60%): جودة الحل المقترح وتوافقه مع المتطلبات',
                'العرض المالي (30%): مناسبة السعر وعدالته',
                'الخبرات السابقة (10%): سجل المشاريع المماثلة',
            ];
        }
        $lines = [];
        foreach ($criteria as $c) {
            if (is_array($c)) {
                $name = $c['criterion'] ?? $c['name'] ?? '';
                $weight = $c['weight'] ?? '';
                $lines[] = trim($name . ($weight ? " ({$weight}%)" : ''));
            } else {
                $lines[] = (string) $c;
            }
        }
        return array_filter($lines);
    }

    private function formatSpecialConditions(?array $conditions): string
    {
        if (empty($conditions)) {
            return 'لا توجد شروط خاصة إضافية.';
        }
        return implode("\n", array_map(fn ($c) => '• ' . $c, $conditions));
    }
}
