<?php

namespace Modules\Tasks\Queries;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Org users eligible to be assigned to a task — same org as the task and
 * not already assigned.
 */
class AvailableAssigneesQuery
{
    public function run(Task $task): Collection
    {
        $assignedIds = $task->assignments->pluck('user_id')->all();

        return User::query()
            ->where('org_id', $task->org_id)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();
    }
}
