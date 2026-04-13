<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Models\TenderSimilarityIgnore;
use App\Models\TenderSimilarityResult;
use App\Services\TenderSimilarityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimilarityController extends Controller
{
    public function __construct(private TenderSimilarityService $similarity) {}

    /**
     * POST /api/v1/tenders/{tender}/similarity/analyze
     * Run full similarity analysis for a tender.
     */
    public function analyze(Request $request, Tender $tender): JsonResponse
    {
        abort_unless($tender->org_id === $request->user()->org_id, 403);

        $results = $this->similarity->analyze($tender);
        $topAlert = $this->buildTopAlert($results);

        return response()->json([
            'tender_id' => $tender->id,
            'top_alert' => $topAlert,
            'matches'   => $results->map(fn ($r) => $this->formatResult($r))->values(),
        ]);
    }

    /**
     * POST /api/v1/tenders/similarity/check-scope
     * Quick scope check before tender creation.
     */
    public function checkScope(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scope_text' => 'required|string|min:10',
            'type'       => 'nullable|string',
        ]);

        $matches = $this->similarity->checkScope(
            $data['scope_text'],
            $request->user()->org_id,
            $data['type'] ?? null,
        );

        $topAlert = null;
        if (! empty($matches)) {
            $top = $matches[0];
            $topAlert = match (true) {
                $top['duplicate_risk']                         => ['type' => 'duplicate_risk', 'message' => 'قد يكون هذا المشروع مكررًا مع مشروع سابق داخل الجهة.'],
                $top['similarity_level'] === 'high_similarity' => ['type' => 'high_similarity', 'message' => "تم العثور على كراسة مشابهة بنسبة {$top['similarity_score']}%. يوصى بمراجعتها."],
                $top['similarity_level'] === 'medium_similarity' => ['type' => 'reuse_opportunity', 'message' => 'تم العثور على كراسة سابقة يمكن استخدامها كمرجع.'],
                default                                        => null,
            };
        }

        return response()->json([
            'top_alert' => $topAlert,
            'matches'   => $matches,
        ]);
    }

    /**
     * GET /api/v1/tenders/{tender}/similarity/results
     * Retrieve cached similarity results.
     */
    public function results(Request $request, Tender $tender): JsonResponse
    {
        abort_unless($tender->org_id === $request->user()->org_id, 403);

        $results = $tender->similarityResults()->with('comparedTender.review')->get();

        // Filter out ignored matches
        $ignored = TenderSimilarityIgnore::where('tender_id', $tender->id)
            ->pluck('matched_tender_id')
            ->toArray();

        $filtered = $results->filter(fn ($r) => ! in_array($r->compared_tender_id, $ignored));

        return response()->json([
            'tender_id' => $tender->id,
            'top_alert' => $this->buildTopAlert($filtered),
            'matches'   => $filtered->map(fn ($r) => $this->formatResult($r))->values(),
            'ignored_count' => count($ignored),
        ]);
    }

    /**
     * GET /api/v1/tenders/{tender}/similarity/compare/{matchedTender}
     * Detailed side-by-side comparison.
     */
    public function compare(Request $request, Tender $tender, Tender $matchedTender): JsonResponse
    {
        abort_unless($tender->org_id === $request->user()->org_id, 403);

        $result = TenderSimilarityResult::where('source_tender_id', $tender->id)
            ->where('compared_tender_id', $matchedTender->id)
            ->first();

        return response()->json([
            'source' => [
                'id'    => $tender->id,
                'title' => $tender->title,
                'type'  => $tender->type_label,
                'scope' => $tender->scope_input,
                'sections' => $tender->sections->map(fn ($s) => ['key' => $s->section_key, 'title' => $s->title, 'excerpt' => mb_substr($s->content, 0, 200)])->toArray(),
            ],
            'compared' => [
                'id'    => $matchedTender->id,
                'title' => $matchedTender->title,
                'type'  => $matchedTender->type_label,
                'scope' => $matchedTender->scope_input,
                'status' => $matchedTender->status_label,
                'compliance_score' => $matchedTender->review?->compliance_score,
                'sections' => $matchedTender->sections->map(fn ($s) => ['key' => $s->section_key, 'title' => $s->title, 'excerpt' => mb_substr($s->content, 0, 200)])->toArray(),
            ],
            'similarity' => $result ? [
                'text_score'       => $result->text_similarity_score,
                'semantic_score'   => $result->semantic_similarity_score,
                'structural_score' => $result->structural_similarity_score,
                'final_score'      => $result->final_similarity_score,
                'level'            => $result->similarity_level,
                'level_label'      => $result->similarity_label,
                'reusable_sections' => $result->reusable_sections,
                'reusable_clauses'  => $result->reusable_clauses,
                'lessons_learned'   => $result->lessons_learned,
                'recommendation'    => $result->recommendation,
            ] : null,
        ]);
    }

    /**
     * POST /api/v1/tenders/{tender}/similarity/ignore
     * Dismiss a similarity alert with a required reason.
     */
    public function ignore(Request $request, Tender $tender): JsonResponse
    {
        abort_unless($tender->org_id === $request->user()->org_id, 403);

        $data = $request->validate([
            'matched_tender_id' => 'required|exists:tenders,id',
            'ignore_reason'     => 'required|string|min:5|max:500',
        ]);

        TenderSimilarityIgnore::updateOrCreate(
            ['tender_id' => $tender->id, 'matched_tender_id' => $data['matched_tender_id']],
            ['ignored_by' => $request->user()->id, 'ignore_reason' => $data['ignore_reason']],
        );

        return response()->json(['success' => true, 'message' => 'تم تجاهل التنبيه.']);
    }

    /**
     * POST /api/v1/tenders/{tender}/reuse/{matchedTender}
     * Copy selected sections/clauses from a prior tender.
     */
    public function reuse(Request $request, Tender $tender, Tender $matchedTender): JsonResponse
    {
        abort_unless($tender->org_id === $request->user()->org_id, 403);

        $data = $request->validate([
            'section_keys' => 'nullable|array',
            'section_keys.*' => 'string',
        ]);

        $keys = $data['section_keys'] ?? [];
        $copied = 0;

        foreach ($matchedTender->sections as $section) {
            if (! empty($keys) && ! in_array($section->section_key, $keys)) continue;

            $existing = $tender->sections()->where('section_key', $section->section_key)->first();
            if ($existing) {
                $existing->update(['content' => $section->content, 'is_edited' => true]);
            } else {
                $tender->sections()->create([
                    'section_key' => $section->section_key,
                    'title'       => $section->title,
                    'content'     => $section->content,
                    'order'       => $tender->sections()->max('order') + 1,
                    'is_edited'   => true,
                ]);
            }
            $copied++;
        }

        return response()->json([
            'success' => true,
            'copied_sections' => $copied,
            'message' => "تم نسخ {$copied} قسم من الكراسة السابقة.",
        ]);
    }

    // ─── Helpers ───

    private function formatResult(TenderSimilarityResult $r): array
    {
        $compared = $r->comparedTender;
        $review = $compared?->review;
        $reusable = $r->reusable_sections ?? [];

        return [
            'tender_id'              => $r->compared_tender_id,
            'title'                  => $compared?->title ?? '—',
            'type_label'             => $compared?->type_label ?? '—',
            'status'                 => $compared?->status ?? '—',
            'status_label'           => $compared?->status_label ?? '—',
            'compliance_score'       => $review?->compliance_score,
            'text_similarity_score'  => $r->text_similarity_score,
            'semantic_similarity_score' => $r->semantic_similarity_score,
            'structural_similarity_score' => $r->structural_similarity_score,
            'final_similarity_score' => $r->final_similarity_score,
            'similarity_level'       => $r->similarity_level,
            'similarity_label'       => $r->similarity_label,
            'duplicate_risk'         => $r->duplicate_risk,
            'matched_segments'       => $reusable['matched_segments'] ?? [],
            'scope_coverage'         => $reusable['scope_coverage'] ?? null,
            'reusable_sections'      => $reusable['sections'] ?? $reusable,
            'lessons_learned'        => $r->lessons_learned,
            'recommendation'         => $r->recommendation,
        ];
    }

    private function buildTopAlert($results): ?array
    {
        if ($results->isEmpty()) return null;
        $top = $results->first();
        $score = $top->final_similarity_score ?? $top['final_similarity_score'] ?? 0;
        $level = $top->similarity_level ?? $top['similarity_level'] ?? '';

        return match (true) {
            $score >= 95 => ['type' => 'exact_match', 'message' => 'يوجد نطاق مطابق أو شبه مطابق لكراسة سابقة داخل نفس الجهة.', 'severity' => 'critical'],
            $score >= 80 => ['type' => 'high_similarity', 'message' => "تم العثور على كراسة مشابهة بنسبة " . round($score) . "%. يوصى بمراجعتها قبل المتابعة.", 'severity' => 'high'],
            $score >= 65 => ['type' => 'reuse_opportunity', 'message' => 'تم العثور على كراسة معتمدة سابقة يمكن استخدامها كمرجع أو كنقطة بداية.', 'severity' => 'medium'],
            default      => ['type' => 'weak_similarity', 'message' => 'تم العثور على كراسات متشابهة بنسبة ضعيفة.', 'severity' => 'low'],
        };
    }
}
