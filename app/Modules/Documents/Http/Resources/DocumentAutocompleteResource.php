<?php

namespace Modules\Documents\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\LegalDocument
 */
class DocumentAutocompleteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'title_en'         => $this->title_en,
            'type_label'       => $this->type_label,
            'reference_number' => $this->reference_number,
        ];
    }
}
