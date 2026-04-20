<?php

namespace Modules\Tenders\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class RejectTenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tender = $this->route('tender');
        $user = $this->user();

        return $user !== null
            && $tender !== null
            && $tender->org_id === $user->org_id
            && $user->hasAtLeastRole(UserRole::LegalCounsel);
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
