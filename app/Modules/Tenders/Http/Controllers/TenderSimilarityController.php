<?php

namespace Modules\Tenders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Models\TenderSimilarityIgnore;
use App\Models\TenderSimilarityResult;
use App\Services\TenderSimilarityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tenders\Actions\IgnoreSimilarityAction;
use Modules\Tenders\Actions\ReuseTenderSectionsAction;
use Modules\Tenders\Http\Requests\CheckScopeRequest;
use Modules\Tenders\Http\Requests\IgnoreSimilarityRequest;
use Modules\Tenders\Http\Requests\ReuseTenderSectionsRequest;

class TenderSimilarityController extends Controller
{
    public function __construct(private readonly TenderSimilarityService $similarity)
    {
    }

    public function analyze(Request $request, Tender $tender): JsonResponse
    {
        $this->ensureSameOrg($request, $tender);

        $results = $this->similarity->analyze($tender);

        return response()->json([
            'tender_id' => $tender->id,
            'top_alert' => $this->buildTopAlert($results),
            'matches'   => $results->map(fn ($result) => $this->formatResult($result))->values(),
        ]);
    }

    public function checkScope(CheckScopeRequest $request): JsonResponse
    {
        $matches = $this->similarity->checkScope(
            $request->string('scope_text'),
            $request->user()->org_id,
            $request->input('type'),
        );

        return response()->json([
            'top_alert' => $this->scopeTopAlert($matches),
            'matches'   => $matches,
        ]);
    }

    public function results(Request $request, Tender $tender): JsonResponse
    {
        $this->ensureSameOrg($request, $tender);

        $results = $tender->similarityResults()->with('comparedTender.review')->get();

        $ignored = TenderSimilarityIgnore::query()
            ->where('tender_id', $tender->id)
            ->pluck('matched_tender_id')
            ->toArray();

        $filtered = $results->filter(fn ($result) => ! in_array($result->compared_tender_id, $ignored, true));

        return response()->json([
            'tender_id'     => $tender->id,
            'top_alert'     => $this->buildTopAlert($filtered),
            'matches'       => $filtered->map(fn ($result) => $this->formatResult($result))->values(),
            'ignored_count' => count($ignored),
        ]);
    }

    public function compare(Request $request, Tender $tender, Tender $matchedTender): JsonResponse
    {
        $this->ensureSameOrg($request, $tender);

        $result = TenderSimilarityResult::query()
            ->where('source_tender_id', $tender->id)
            ->where('compared_tender_id', $matchedTender->id)
            ->first();

        return response()->json([
            'source'     => $this->summarizeTender($tender),
            'compared'   => $this->summarizeTender($matchedTender, withReview: true),
            'similarity' => $result !== null ? $this->formatSimilarity($result) : null,
        ]);
    }

    public function ignore(IgnoreSimilarityRequest $request, Tender $tender, IgnoreSimilarityAction $action): JsonResponse
    {
        $action->execute(
            $tender,
            $request->user(),
            (int) $request->input('matched_tender_id'),
            $request->string('ignore_reason'),
        );

        return response()->json([
            'success' => true,
            'message' => __('tenders.flash.similarity_ignored'),
        ]);
    }

    public function reuse(ReuseTenderSectionsRequest $request, Tender $tender, Tender $matchedTender, ReuseTenderSectionsAction $action): JsonResponse
    {
        $copied = $action->execute($tender, $matchedTender, $request->input('section_keys', []));

        return response()->json([
            'success'         => true,
            'copied_sections' => $copied,
            'message'         => __('tenders.flash.sections_reused', ['count' => $copied]),
        ]);
    }

    private function ensureSameOrg(Request $request, Tender $tender): void
    {
        abort_if($tender->org_id !== $request->user()->org_id, 403);
    }

    private function summarizeTender(Tender $tender, bool $withReview = false): array
    {
        $payload = [
            'id'       => $tender->id,
            'title'    => $tender->title,
            'type'     => $tender->type_label,
            'scope'    => $tender->scope_input,
            'sections' => $tender->sections->map(fn ($section) => [
                'key'     => $section->section_key,
                'title'   => $section->title,
                'excerpt' => mb_substr($section->content, 0, 200),
            ])->toArray(),
        ];

        if ($withReview) {
            $payload['status']           = $tender->status_label;
            $payload['compliance_score'] = $tender->review?->compliance_score;
        }

        return $payload;
    }

    private function formatSimilarity(TenderSimilarityResult $result): array
    {
        return [
            'text_score'        => $result->text_similarity_score,
            'semantic_score'    => $result->semantic_similarity_score,
            'structural_score'  => $result->structural_similarity_score,
            'final_score'       => $result->final_similarity_score,
            'level'             => $result->similarity_level,
            'level_label'       => $result->similarity_label,
            'reusable_sections' => $result->reusable_sections,
            'reusable_clauses'  => $result->reusable_clauses,
            'lessons_learned'   => $result->lessons_learned,
            'recommendation'    => $result->recommendation,
        ];
    }

    private function formatResult(TenderSimilarityResult $result): array
    {
        $compared = $result->comparedTender;
        $review   = $compared?->review;
        $reusable = $result->reusable_sections ?? [];

        return [
            'tender_id'                   => $result->compared_tender_id,
            'title'                       => $compared?->title ?? '—',
            'type_label'                  => $compared?->type_label ?? '—',
            'status'                      => $compared?->status ?? '—',
            'status_label'                => $compared?->status_label ?? '—',
            'compliance_score'            => $review?->compliance_score,
            'text_similarity_score'       => $result->text_similarity_score,
            'semantic_similarity_score'   => $result->semantic_similarity_score,
            'structural_similarity_score' => $result->structural_similarity_score,
            'final_similarity_score'      => $result->final_similarity_score,
            'similarity_level'            => $result->similarity_level,
            'similarity_label'            => $result->similarity_label,
            'duplicate_risk'              => $result->duplicate_risk,
            'matched_segments'            => $reusable['matched_segments'] ?? [],
            'scope_coverage'              => $reusable['scope_coverage'] ?? null,
            'reusable_sections'           => $reusable['sections'] ?? $reusable,
            'lessons_learned'             => $result->lessons_learned,
            'recommendation'              => $result->recommendation,
        ];
    }

    private function buildTopAlert($results): ?array
    {
        if ($results->isEmpty()) {
            return null;
        }

        $top   = $results->first();
        $score = $top->final_similarity_score ?? $top['final_similarity_score'] ?? 0;

        return match (true) {
            $score >= 95 => ['type' => 'exact_match',       'message' => __('tenders.similarity.exact_match'),     'severity' => 'critical'],
            $score >= 80 => ['type' => 'high_similarity',   'message' => __('tenders.similarity.high', ['score' => round($score)]), 'severity' => 'high'],
            $score >= 65 => ['type' => 'reuse_opportunity', 'message' => __('tenders.similarity.reuse_opportunity'), 'severity' => 'medium'],
            default      => ['type' => 'weak_similarity',   'message' => __('tenders.similarity.weak'),             'severity' => 'low'],
        };
    }

    private function scopeTopAlert(array $matches): ?array
    {
        if (empty($matches)) {
            return null;
        }

        $top = $matches[0];

        return match (true) {
            $top['duplicate_risk']                             => ['type' => 'duplicate_risk',    'message' => __('tenders.similarity.scope_duplicate')],
            $top['similarity_level'] === 'high_similarity'     => ['type' => 'high_similarity',   'message' => __('tenders.similarity.scope_high', ['score' => $top['similarity_score']])],
            $top['similarity_level'] === 'medium_similarity'   => ['type' => 'reuse_opportunity', 'message' => __('tenders.similarity.scope_medium')],
            default                                            => null,
        };
    }
}
