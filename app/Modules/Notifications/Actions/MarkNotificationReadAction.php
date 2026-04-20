<?php

namespace Modules\Notifications\Actions;

use App\Models\AppNotification;

class MarkNotificationReadAction
{
    public function execute(AppNotification $notification): void
    {
        $notification->update(['read_at' => now()]);
    }
}
