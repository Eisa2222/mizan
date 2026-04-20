<?php

namespace Modules\Documents\Http\Requests;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;

class AutocompleteDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LegalDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q'       => ['nullable', 'string', 'max:255'],
            'exclude' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function term(): string
    {
        return trim((string) $this->query('q', ''));
    }

    public function excludeId(): int
    {
        return (int) $this->query('exclude', 0);
    }
}
