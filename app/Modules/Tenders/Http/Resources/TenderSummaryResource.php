<?php

namespace Modules\Tenders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact projection of a Tender used inside similarity comparison payloads.
 * Set the `withReview` meta flag via `additional()` to include status and
 * compliance score — omitted by default to keep the shape stable for clients
 * that only need the top-level tender identity.
 *
 * @property \App\Models\Tender $resource
 */
class TenderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = [
            'id'       => $this->resource->id,
            'title'    => $this->resource->title,
            'type'     => $this->resource->type_label,
            'scope'    => $this->resource->scope_input,
            'sections' => $this->resource->sections->map(fn ($section) => [
                'key'     => $section->section_key,
                'title'   => $section->title,
                'excerpt' => mb_substr($section->content, 0, 200),
            ])->toArray(),
        ];

        if ($this->additional['with_review'] ?? false) {
            $payload['status']           = $this->resource->status_label;
            $payload['compliance_score'] = $this->resource->review?->compliance_score;
        }

        return $payload;
    }
}
