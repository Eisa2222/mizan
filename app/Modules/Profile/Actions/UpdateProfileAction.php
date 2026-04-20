<?php

namespace Modules\Profile\Actions;

use App\Models\User;

class UpdateProfileAction
{
    /**
     * @param  array{name:string, email:string}  $data
     */
    public function execute(User $user, array $data): User
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }
}
