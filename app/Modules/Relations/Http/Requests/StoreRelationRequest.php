<?php

namespace Modules\Relations\Http\Requests;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return [
            'to_document_id' => ['required', 'integer', 'exists:legal_documents,id'],
            'relation_type'  => ['required', 'string', Rule::in(array_keys(LegalDocument::RELATION_TYPES))],
            'note'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
