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
use Modules\Tenders\Http\Resources\TenderSimilarityResultResource;
use Modules\Tenders\Http\Resources\TenderSimilarityScoreResource;
use Modules\Tenders\Http\Resources\TenderSummaryResource;

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
            'matches'   => TenderSimilarityResultResource::collection($results),
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

        $filtered = $results->reject(fn ($result) => in_array($result->compared_tender_id, $ignored, true))->values();

        return response()->json([
            'tender_id'     => $tender->id,
            'top_alert'     => $this->buildTopAlert($filtered),
            'matches'       => TenderSimilarityResultResource::collection($filtered),
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
            'source'     => (new TenderSummaryResource($tender))->toArray($request),
            'compared'   => (new TenderSummaryResource($matchedTender))->additional(['with_review' => true])->toArray($request),
            'similarity' => $result !== null ? (new TenderSimilarityScoreResource($result))->toArray($request) : null,
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
