<?php

namespace Modules\Tenders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenderSectionRequest extends FormRequest
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
            'content' => ['required', 'string'],
        ];
    }
}
