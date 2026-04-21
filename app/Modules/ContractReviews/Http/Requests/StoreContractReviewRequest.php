<?php

namespace Modules\ContractReviews\Http\Requests;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;

class StoreContractReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LegalDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title'   => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'file'    => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ];
    }
}
