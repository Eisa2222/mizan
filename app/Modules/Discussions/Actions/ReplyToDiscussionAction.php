<?php

namespace Modules\Discussions\Actions;

use App\Models\Discussion;
use App\Models\DiscussionReply;
use App\Models\User;

class ReplyToDiscussionAction
{
    public function execute(Discussion $discussion, User $user, string $body): DiscussionReply
    {
        return $discussion->replies()->create([
            'user_id' => $user->id,
            'body'    => $body,
        ]);
    }
}
