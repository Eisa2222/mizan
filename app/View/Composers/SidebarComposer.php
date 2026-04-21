<?php

namespace App\View\Composers;

use App\Models\AppNotification;
use App\Models\Task;
use Illuminate\View\View;

class SidebarComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if ($user === null) {
            $view->with([
                'activeTaskCount'       => 0,
                'unreadNotificationCount' => 0,
            ]);

            return;
        }

        $view->with([
            'activeTaskCount' => $user->org_id !== null
                ? Task::query()
                    ->where('org_id', $user->org_id)
                    ->whereIn('status', [1, 2, 3])
                    ->count()
                : 0,

            'unreadNotificationCount' => AppNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }
}
