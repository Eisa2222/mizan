<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Discussion;
use App\Models\User;

class DiscussionPolicy
{
    private function sameOrg(User $user, Discussion $discussion): bool
    {
        return $discussion->document?->org_id !== null
            && $discussion->document->org_id === $user->org_id;
    }

    public function view(User $user, Discussion $discussion): bool
    {
        return $this->sameOrg($user, $discussion);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::DiscussionsCreate);
    }

    public function reply(User $user, Discussion $discussion): bool
    {
        return $user->hasPermission(Permission::DiscussionsReply)
            && $this->sameOrg($user, $discussion);
    }

    public function delete(User $user, Discussion $discussion): bool
    {
        return $user->hasPermission(Permission::DiscussionsDelete)
            && $discussion->user_id === $user->id;
    }
}
