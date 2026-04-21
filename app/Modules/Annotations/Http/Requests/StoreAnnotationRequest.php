<?php

namespace Modules\Annotations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document !== null
            && $this->user() !== null
            && $document->org_id === $this->user()->org_id;
    }

    public function rules(): array
    {
        return [
            'selected_text' => ['required', 'string', 'max:5000'],
            'comment'       => ['nullable', 'string', 'max:2000'],
            'color'         => ['required', 'in:gold,blue,green,red'],
            'visibility'    => ['nullable', 'in:public,org,private'],
        ];
    }
}
