<?php

namespace Modules\TenderReviews\Http\Requests;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenderReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LegalDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'tender_type' => ['nullable', 'string', 'max:100'],
            'sector'      => ['nullable', 'string', 'max:100'],
            'summary'     => ['nullable', 'string', 'max:5000'],
            'file'        => ['required', 'file', 'mimes:pdf,doc,docx', 'max:40960'],
        ];
    }
}
