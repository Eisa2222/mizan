<?php

namespace Modules\Admin\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Admin\DTOs\NewOrganizationData;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::SuperAdmin) ?? false;
    }

    public function rules(): array
    {
        return [
            'name_ar'        => ['required', 'string', 'max:255'],
            'name_en'        => ['nullable', 'string', 'max:255'],
            'domain'         => ['required', 'string', 'max:100', 'unique:organizations,domain'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:200'],
            'website'        => ['nullable', 'string', 'max:300'],
            'address'        => ['nullable', 'string', 'max:500'],

            'admin_name'     => ['required', 'string', 'max:255'],
            'admin_email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', Password::min(8)],
            'admin_role'     => ['required', Rule::enum(UserRole::class)],
        ];
    }

    public function toData(): NewOrganizationData
    {
        return new NewOrganizationData(
            nameAr:        $this->string('name_ar'),
            nameEn:        $this->input('name_en'),
            domain:        $this->string('domain'),
            phone:         $this->input('phone'),
            email:         $this->input('email'),
            website:       $this->input('website'),
            address:       $this->input('address'),
            adminName:     $this->string('admin_name'),
            adminEmail:    $this->string('admin_email'),
            adminPassword: $this->string('admin_password'),
            adminRole:     UserRole::from($this->string('admin_role')),
        );
    }
}
