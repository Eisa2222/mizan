<?php

namespace Modules\Memos\Http\Requests;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LegalDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'file'    => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ];
    }
}
