<?php

namespace Modules\Assistant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'document_id' => ['nullable', 'integer', 'exists:legal_documents,id'],
            'title'       => ['nullable', 'string', 'max:255'],
        ];
    }
}
