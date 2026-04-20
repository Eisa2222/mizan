<?php

namespace Modules\Assistant\Actions;

use App\Models\AiConversation;
use App\Models\User;

class StartAssistantConversationAction
{
    public function execute(User $user, ?int $documentId, ?string $title): AiConversation
    {
        return AiConversation::create([
            'user_id'     => $user->id,
            'document_id' => $documentId,
            'title'       => $title,
        ]);
    }
}
