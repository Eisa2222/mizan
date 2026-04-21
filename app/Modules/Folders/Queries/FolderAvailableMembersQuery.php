<?php

namespace Modules\Folders\Queries;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Org users eligible to be added as folder members — excludes the owner and
 * anyone already a member.
 */
class FolderAvailableMembersQuery
{
    public function run(Folder $folder): Collection
    {
        $excludeIds = $folder->members->pluck('user_id')->push($folder->owner_id);

        return User::query()
            ->where('org_id', $folder->org_id)
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();
    }
}
