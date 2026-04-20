<?php

namespace Modules\Tenders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IgnoreSimilarityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tender = $this->route('tender');

        return $tender !== null
            && $this->user() !== null
            && $tender->org_id === $this->user()->org_id;
    }

    public function rules(): array
    {
        return [
            'matched_tender_id' => ['required', 'exists:tenders,id'],
            'ignore_reason'     => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
