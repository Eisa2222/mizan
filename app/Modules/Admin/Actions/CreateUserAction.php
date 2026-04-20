<?php

namespace Modules\Admin\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\DTOs\NewUserData;

class CreateUserAction
{
    public function execute(NewUserData $data): User
    {
        return User::create([
            'name'     => $data->name,
            'email'    => $data->email,
            'password' => Hash::make($data->password),
            'org_id'   => $data->orgId,
            'role'     => $data->role->value,
        ]);
    }
}
