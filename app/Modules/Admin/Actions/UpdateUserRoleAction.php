<?php

namespace Modules\Admin\Actions;

use App\Enums\UserRole;
use App\Models\User;

class UpdateUserRoleAction
{
    public function execute(User $user, UserRole $role): User
    {
        $user->update(['role' => $role->value]);

        return $user;
    }
}
