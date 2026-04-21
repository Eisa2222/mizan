<?php

namespace Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tasks\Actions\AssignUserToTaskAction;
use Modules\Tasks\Actions\CommentOnTaskAction;
use Modules\Tasks\Actions\CreateTaskAction;
use Modules\Tasks\Actions\UnassignUserFromTaskAction;
use Modules\Tasks\Actions\UpdateTaskStatusAction;
use Modules\Tasks\Http\Requests\AssignTaskRequest;
use Modules\Tasks\Http\Requests\CommentOnTaskRequest;
use Modules\Tasks\Http\Requests\StoreTaskRequest;
use Modules\Tasks\Http\Requests\UpdateTaskStatusRequest;
use Modules\Tasks\Queries\AvailableAssigneesQuery;
use Modules\Tasks\Queries\TasksForOrgQuery;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, TasksForOrgQuery $query): View
    {
        $this->authorize('viewAny', Task::class);

        $tasks = $query->paginate($request->user());

        return view('tasks.index', compact('tasks'));
    }

    public function show(Task $task, AvailableAssigneesQuery $availableUsersQuery): View
    {
        $this->authorize('view', $task);

        $task->load([
            'creator',
            'assignments.user',
            'comments.user'   => fn ($q) => $q->orderBy('created_at'),
            'activities.user' => fn ($q) => $q->latest(),
        ]);

        return view('tasks.show', [
            'task'           => $task,
            'availableUsers' => $availableUsersQuery->run($task),
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTaskAction $action): RedirectResponse
    {
        $action->execute($request->toData());

        return redirect()
            ->route('tasks.index')
            ->with('success', __('tasks.flash.created'));
    }

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task, UpdateTaskStatusAction $action): JsonResponse|RedirectResponse
    {
        $action->execute($task, (int) $request->input('status'), $request->user());

        if ($request->wantsJson()) {
            return response()->json(['status' => $task->status]);
        }

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()
            ->route('tasks.index')
            ->with('success', __('tasks.flash.deleted'));
    }

    public function assign(AssignTaskRequest $request, Task $task, AssignUserToTaskAction $action): RedirectResponse
    {
        $target = User::findOrFail($request->input('user_id'));

        $action->execute($task, $target, (int) $request->input('role'), $request->user());

        return back()->with('success', __('tasks.flash.assigned'));
    }

    public function unassign(Request $request, Task $task, User $user, UnassignUserFromTaskAction $action): RedirectResponse
    {
        $this->authorize('update', $task);

        $action->execute($task, $user, $request->user());

        return back()->with('success', __('tasks.flash.unassigned'));
    }

    public function comment(CommentOnTaskRequest $request, Task $task, CommentOnTaskAction $action): RedirectResponse
    {
        $action->execute($task, $request->string('body'), $request->user());

        return back()->with('success', __('tasks.flash.commented'));
    }
}
