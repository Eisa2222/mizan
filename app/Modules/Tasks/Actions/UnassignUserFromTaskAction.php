<?php

namespace Modules\Tasks\Actions;

use App\Models\Task;
use App\Models\User;

class UnassignUserFromTaskAction
{
    public function execute(Task $task, User $target, User $actor): void
    {
        $task->assignments()->where('user_id', $target->id)->delete();

        $task->activities()->create([
            'user_id'  => $actor->id,
            'action'   => 'task.unassigned',
            'metadata' => ['user_id' => $target->id],
        ]);
    }
}
