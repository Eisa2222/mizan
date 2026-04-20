<?php

namespace Modules\Tenders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckScopeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'scope_text' => ['required', 'string', 'min:10'],
            'type'       => ['nullable', 'string'],
        ];
    }
}
