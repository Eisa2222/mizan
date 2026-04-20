<?php

namespace Modules\Notifications\Actions;

use App\Models\AppNotification;
use App\Models\User;

class MarkAllNotificationsReadAction
{
    public function execute(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
