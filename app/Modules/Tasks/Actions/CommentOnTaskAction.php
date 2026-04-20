<?php

namespace Modules\Tasks\Actions;

use App\Models\AppNotification;
use App\Models\Task;
use App\Models\User;

/**
 * Adds a comment to a task, records the activity, and notifies all assignees
 * plus the creator (except the commenter).
 */
class CommentOnTaskAction
{
    public function execute(Task $task, string $body, User $actor): void
    {
        $task->comments()->create([
            'user_id' => $actor->id,
            'body'    => $body,
        ]);

        $task->activities()->create([
            'user_id'  => $actor->id,
            'action'   => 'task.commented',
            'metadata' => null,
        ]);

        $recipients = $task->assignments()
            ->pluck('user_id')
            ->push($task->created_by_id)
            ->unique()
            ->reject(fn ($id) => $id === $actor->id);

        foreach ($recipients as $recipientId) {
            AppNotification::notify(
                $recipientId,
                'task.commented',
                __('tasks.notifications.commented_title'),
                $task->title,
                ['task_id' => $task->id, 'link' => route('tasks.show', $task)],
            );
        }
    }
}
