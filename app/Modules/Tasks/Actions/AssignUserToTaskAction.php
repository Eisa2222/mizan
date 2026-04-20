<?php

namespace Modules\Tasks\Actions;

use App\Models\AppNotification;
use App\Models\Task;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AssignUserToTaskAction
{
    public function execute(Task $task, User $target, int $role, User $actor): void
    {
        if ($target->org_id !== $task->org_id) {
            throw new UnprocessableEntityHttpException(__('tasks.errors.user_not_same_org'));
        }

        $task->assignments()->updateOrCreate(
            ['user_id' => $target->id],
            ['role' => $role, 'assigned_by' => $actor->id],
        );

        $task->activities()->create([
            'user_id'  => $actor->id,
            'action'   => 'task.assigned',
            'metadata' => ['user_id' => $target->id, 'role' => $role],
        ]);

        if ($target->id !== $actor->id) {
            AppNotification::notify(
                $target->id,
                'task.assigned',
                __('tasks.notifications.assigned_title'),
                $task->title,
                ['task_id' => $task->id, 'link' => route('tasks.show', $task)],
            );
        }
    }
}
