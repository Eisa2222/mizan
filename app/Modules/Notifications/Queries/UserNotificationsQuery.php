<?php

namespace Modules\Notifications\Queries;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserNotificationsQuery
{
    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }
}
