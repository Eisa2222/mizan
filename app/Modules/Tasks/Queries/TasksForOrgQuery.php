<?php

namespace Modules\Tasks\Queries;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TasksForOrgQuery
{
    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Task::query()
            ->where('org_id', $user->org_id)
            ->withCount(['assignments', 'comments'])
            ->latest()
            ->paginate($perPage);
    }
}
