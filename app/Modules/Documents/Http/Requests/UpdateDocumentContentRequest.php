<?php

namespace Modules\Documents\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('document')) ?? false;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:1000000'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
