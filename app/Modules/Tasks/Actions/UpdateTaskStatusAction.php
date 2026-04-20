<?php

namespace Modules\Tasks\Actions;

use App\Models\Task;
use App\Models\User;

class UpdateTaskStatusAction
{
    public function execute(Task $task, int $status, User $actor): Task
    {
        $task->changeStatus($status, $actor->id);

        return $task;
    }
}
