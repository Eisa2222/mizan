<?php

namespace Modules\Admin\Queries;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UsersListQuery
{
    public function paginate(?int $orgId, int $perPage = 30): LengthAwarePaginator
    {
        return User::query()
            ->with('organization')
            ->when($orgId, fn ($q, int $id) => $q->where('org_id', $id))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}
