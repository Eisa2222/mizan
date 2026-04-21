<?php

namespace Modules\Documents\Http\Requests;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;

class IndexDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LegalDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q'    => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'integer', 'between:1,7'],
        ];
    }

    public function search(): ?string
    {
        $term = trim((string) $this->query('q', ''));

        return $term === '' ? null : $term;
    }

    public function typeFilter(): ?int
    {
        $type = $this->query('type');

        return $type === null || $type === '' ? null : (int) $type;
    }
}
