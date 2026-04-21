<?php

namespace Modules\Discussions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyDiscussionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $discussion = $this->route('discussion');

        return $discussion !== null
            && $this->user() !== null
            && $discussion->document->org_id === $this->user()->org_id;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
