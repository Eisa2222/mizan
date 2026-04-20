<?php

namespace Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Notifications\Actions\MarkAllNotificationsReadAction;
use Modules\Notifications\Actions\MarkNotificationReadAction;
use Modules\Notifications\Queries\UserNotificationsQuery;

class NotificationController extends Controller
{
    public function index(Request $request, UserNotificationsQuery $query): View
    {
        return view('notifications.index', [
            'notifications' => $query->paginate($request->user()),
        ]);
    }

    public function markRead(Request $request, AppNotification $notification, MarkNotificationReadAction $action): RedirectResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403);

        $action->execute($notification);

        $link = $notification->data['link'] ?? null;

        return $link !== null ? redirect($link) : back();
    }

    public function markAllRead(Request $request, MarkAllNotificationsReadAction $action): RedirectResponse
    {
        $action->execute($request->user());

        return back()->with('success', __('notifications.flash.all_read'));
    }
}
