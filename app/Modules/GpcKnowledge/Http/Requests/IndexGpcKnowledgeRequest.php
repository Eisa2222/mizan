<?php

namespace Modules\GpcKnowledge\Http\Requests;

use App\Models\GpcArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexGpcKnowledgeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source' => ['nullable', Rule::in(array_keys(GpcArticle::SOURCES))],
            'q'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function source(): ?string
    {
        $source = $this->query('source');

        return $source === null || $source === '' ? null : (string) $source;
    }

    public function term(): ?string
    {
        $term = trim((string) $this->query('q', ''));

        return $term === '' ? null : $term;
    }
}
