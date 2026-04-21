<?php

namespace Modules\Discussions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscussionRequest extends FormRequest
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
            'title'      => ['required', 'string', 'max:200'],
            'body'       => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', 'in:public,org,private'],
        ];
    }
}
