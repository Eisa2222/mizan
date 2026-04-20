<?php

namespace Modules\Watchlist\Queries;

use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WatchlistForUserQuery
{
    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Watchlist::query()
            ->with('document')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }
}
