<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FoldersView);
    }

    public function view(User $user, Folder $folder): bool
    {
        return $user->hasPermission(Permission::FoldersView)
            && $folder->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FoldersCreate);
    }

    public function update(User $user, Folder $folder): bool
    {
        return $user->hasPermission(Permission::FoldersUpdate)
            && $folder->owner_id === $user->id;
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $user->hasPermission(Permission::FoldersDelete)
            && $folder->owner_id === $user->id;
    }

    public function manageMembers(User $user, Folder $folder): bool
    {
        return $user->hasPermission(Permission::FoldersManageMembers)
            && $folder->owner_id === $user->id;
    }
}
