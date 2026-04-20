<?php

namespace Modules\Tenders\Queries;

use App\Models\Tender;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TendersForOrgQuery
{
    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Tender::query()
            ->where('org_id', $user->org_id)
            ->with(['creator', 'review'])
            ->latest()
            ->paginate($perPage);
    }
}
