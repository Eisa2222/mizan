<?php

namespace Modules\Tasks\Actions;

use App\Models\Task;
use Modules\Tasks\DTOs\NewTaskData;

class CreateTaskAction
{
    public function execute(NewTaskData $data): Task
    {
        $task = Task::create([
            'org_id'        => $data->orgId,
            'created_by_id' => $data->createdById,
            'title'         => $data->title,
            'description'   => $data->description,
            'priority'      => $data->priority,
            'due_date'      => $data->dueDate,
            'document_id'   => $data->documentId,
            'status'        => 1,
        ]);

        $task->activities()->create([
            'user_id'  => $data->createdById,
            'action'   => 'task.created',
            'metadata' => null,
        ]);

        return $task;
    }
}
