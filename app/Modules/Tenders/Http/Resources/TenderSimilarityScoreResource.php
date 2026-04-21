<?php

namespace Modules\Tenders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Similarity score breakdown for a head-to-head tender comparison. Used by
 * the `/tenders/{a}/similarity/{b}` compare endpoint where the caller already
 * holds both tenders and just needs the score metadata.
 *
 * @property \App\Models\TenderSimilarityResult $resource
 */
class TenderSimilarityScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'text_score'        => $this->resource->text_similarity_score,
            'semantic_score'    => $this->resource->semantic_similarity_score,
            'structural_score'  => $this->resource->structural_similarity_score,
            'final_score'       => $this->resource->final_similarity_score,
            'level'             => $this->resource->similarity_level,
            'level_label'       => $this->resource->similarity_label,
            'reusable_sections' => $this->resource->reusable_sections,
            'reusable_clauses'  => $this->resource->reusable_clauses,
            'lessons_learned'   => $this->resource->lessons_learned,
            'recommendation'    => $this->resource->recommendation,
        ];
    }
}
