<?php

namespace Modules\Profile\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteProfileRequest extends FormRequest
{
    protected $errorBag = 'userDeletion';

    public function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
        ];
    }
}
