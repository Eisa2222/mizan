<?php

namespace Modules\Discussions\Actions;

use App\Models\Discussion;
use App\Models\LegalDocument;
use App\Models\User;

class CreateDiscussionAction
{
    /**
     * @param  array{title:string, body:string, visibility:?string}  $data
     */
    public function execute(LegalDocument $document, User $user, array $data): Discussion
    {
        return Discussion::create([
            'document_id' => $document->id,
            'user_id'     => $user->id,
            'title'       => $data['title'],
            'body'        => $data['body'],
            'visibility'  => $data['visibility'] ?? 'org',
        ]);
    }
}
