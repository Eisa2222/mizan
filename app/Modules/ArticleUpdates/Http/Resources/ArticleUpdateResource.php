<?php

namespace Modules\ArticleUpdates\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ArticleUpdate
 */
class ArticleUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'article_label'   => $this->article_label,
            'update_date'     => $this->update_date->format('Y-m-d'),
            'decree_number'   => $this->decree_number,
            'decree_url'      => $this->decree_url,
            'body'            => $this->body,
            'auto_generated'  => (bool) $this->auto_generated,
            'creator'         => $this->creator?->name,
            'source_document' => $this->sourceDocument
                ? ['id' => $this->sourceDocument->id, 'title' => $this->sourceDocument->title]
                : null,
        ];
    }
}
