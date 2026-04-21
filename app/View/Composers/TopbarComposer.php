<?php

namespace App\View\Composers;

use App\Models\AppNotification;
use Illuminate\View\View;

class TopbarComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if ($user === null) {
            $view->with([
                'unreadCount'  => 0,
                'latestNotifs' => collect(),
            ]);

            return;
        }

        $view->with([
            'unreadCount' => AppNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count(),

            'latestNotifs' => AppNotification::query()
                ->where('user_id', $user->id)
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
