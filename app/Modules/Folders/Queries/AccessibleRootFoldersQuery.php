<?php

namespace Modules\Folders\Queries;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Root folders inside the user's org that the user either owns or is a member of.
 * Eager-loads counts used by the folders index card UI.
 */
class AccessibleRootFoldersQuery
{
    public function run(User $user): Collection
    {
        return Folder::query()
            ->where('org_id', $user->org_id)
            ->whereNull('parent_id')
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id));
            })
            ->withCount(['documents', 'children', 'members'])
            ->latest()
            ->get();
    }
}
