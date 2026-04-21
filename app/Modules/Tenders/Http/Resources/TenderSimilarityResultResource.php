<?php

namespace Modules\Tenders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape of a single similarity match used by the tender similarity
 * analyze/results endpoints. Defensively handles the case where the
 * compared tender was deleted by returning "—" placeholders rather
 * than null-deref'ing.
 *
 * @property \App\Models\TenderSimilarityResult $resource
 */
class TenderSimilarityResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $compared = $this->resource->comparedTender;
        $review   = $compared?->review;
        $reusable = $this->resource->reusable_sections ?? [];

        return [
            'tender_id'                   => $this->resource->compared_tender_id,
            'title'                       => $compared?->title ?? '—',
            'type_label'                  => $compared?->type_label ?? '—',
            'status'                      => $compared?->status ?? '—',
            'status_label'                => $compared?->status_label ?? '—',
            'compliance_score'            => $review?->compliance_score,
            'text_similarity_score'       => $this->resource->text_similarity_score,
            'semantic_similarity_score'   => $this->resource->semantic_similarity_score,
            'structural_similarity_score' => $this->resource->structural_similarity_score,
            'final_similarity_score'      => $this->resource->final_similarity_score,
            'similarity_level'            => $this->resource->similarity_level,
            'similarity_label'            => $this->resource->similarity_label,
            'duplicate_risk'              => $this->resource->duplicate_risk,
            'matched_segments'            => $reusable['matched_segments'] ?? [],
            'scope_coverage'              => $reusable['scope_coverage'] ?? null,
            'reusable_sections'           => $reusable['sections'] ?? $reusable,
            'lessons_learned'             => $this->resource->lessons_learned,
            'recommendation'              => $this->resource->recommendation,
        ];
    }
}
