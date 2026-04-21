<?php

namespace Modules\Admin\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Admin\DTOs\NewUserData;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::SuperAdmin) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'org_id'   => ['required', 'exists:organizations,id'],
            'role'     => ['required', Rule::enum(UserRole::class)],
        ];
    }

    public function toData(): NewUserData
    {
        return new NewUserData(
            name:     $this->string('name'),
            email:    $this->string('email'),
            password: $this->string('password'),
            orgId:    (int) $this->input('org_id'),
            role:     UserRole::from($this->string('role')),
        );
    }
}
