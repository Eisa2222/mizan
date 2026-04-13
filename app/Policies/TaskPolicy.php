<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    private function sameOrg(User $user, Task $task): bool
    {
        return $user->org_id !== null && $user->org_id === $task->org_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAtLeastRole(UserRole::ReadOnly);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->sameOrg($user, $task) && $user->hasAtLeastRole(UserRole::ReadOnly);
    }

    public function create(User $user): bool
    {
        return $user->hasAtLeastRole(UserRole::OrgUser);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->sameOrg($user, $task) && $user->hasAtLeastRole(UserRole::OrgUser);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->sameOrg($user, $task) && (
            $user->hasAtLeastRole(UserRole::OrgAdmin)
            || $task->created_by_id === $user->id
        );
    }
}
