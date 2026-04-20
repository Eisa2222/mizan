<?php

namespace Modules\Folders\Actions;

use App\Models\Folder;
use App\Models\User;

class CreateFolderAction
{
    /**
     * @param  array{name:string, description:?string, parent_id:?int}  $data
     */
    public function execute(User $user, array $data): Folder
    {
        return Folder::create([
            'org_id'      => $user->org_id,
            'owner_id'    => $user->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'parent_id'   => $data['parent_id'] ?? null,
        ]);
    }
}
