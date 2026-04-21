<?php

namespace Modules\Folders\Actions;

use App\Models\Folder;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AddFolderMemberAction
{
    public function execute(Folder $folder, User $target, string $role): void
    {
        if ($target->org_id !== $folder->org_id) {
            throw new UnprocessableEntityHttpException(__('folders.errors.member_not_same_org'));
        }

        $folder->members()->updateOrCreate(
            ['user_id' => $target->id],
            ['role'    => $role],
        );
    }
}
