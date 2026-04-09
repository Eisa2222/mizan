<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);
        $tasks = Task::query()
            ->where('org_id', $request->user()->org_id)
            ->withCount(['assignments', 'comments'])
            ->latest()
            ->get();

        return view('tasks.index', compact('tasks'));
    }

    public function show(Request $request, Task $task)
    {
        $this->authorize('view', $task);
        $task->load([
            'creator',
            'assignments.user',
            'comments.user' => fn ($q) => $q->orderBy('created_at'),
            'activities.user' => fn ($q) => $q->latest(),
        ]);

        // Org users to assign (excluding already-assigned)
        $assignedIds = $task->assignments->pluck('user_id')->all();
        $availableUsers = User::where('org_id', $request->user()->org_id)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();

        return view('tasks.show', compact('task', 'availableUsers'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Task::class);
        $data = $request->validate([
            'title' => 'required|string|max:300',
            'description' => 'nullable|string|max:5000',
            'priority' => 'required|integer|between:1,4',
            'due_date' => 'nullable|date',
            'document_id' => 'nullable|integer|exists:legal_documents,id',
        ]);

        $data['org_id'] = $request->user()->org_id;
        $data['created_by_id'] = $request->user()->id;
        $data['status'] = 1;

        $task = Task::create($data);

        $task->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'task.created',
            'metadata' => null,
        ]);

        return redirect()->route('tasks.index')->with('success', 'تم إنشاء المهمة');
    }

    public function updateStatus(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => 'required|integer|between:1,5',
        ]);

        $task->changeStatus($data['status'], $request->user()->id);

        if ($request->wantsJson()) {
            return response()->json(['status' => $task->status]);
        }

        return back();
    }

    public function destroy(Request $request, Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();
        return redirect()->route('tasks.index')->with('success', 'تم حذف المهمة');
    }

    public function assign(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|integer|between:1,3',
        ]);

        // Ensure target user is in same org
        $target = User::findOrFail($data['user_id']);
        abort_if($target->org_id !== $task->org_id, 422, 'المستخدم ليس من نفس المؤسسة');

        $task->assignments()->updateOrCreate(
            ['user_id' => $data['user_id']],
            ['role' => $data['role'], 'assigned_by' => $request->user()->id]
        );

        $task->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'task.assigned',
            'metadata' => ['user_id' => $data['user_id'], 'role' => $data['role']],
        ]);

        if ($target->id !== $request->user()->id) {
            AppNotification::notify(
                $target->id,
                'task.assigned',
                'تم تكليفك بمهمة',
                $task->title,
                ['task_id' => $task->id, 'link' => route('tasks.show', $task)]
            );
        }

        return back()->with('success', 'تم التكليف');
    }

    public function unassign(Request $request, Task $task, int $userId)
    {
        $this->authorize('update', $task);

        $task->assignments()->where('user_id', $userId)->delete();

        $task->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'task.unassigned',
            'metadata' => ['user_id' => $userId],
        ]);

        return back()->with('success', 'تم إلغاء التكليف');
    }

    public function comment(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $task->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'task.commented',
            'metadata' => null,
        ]);

        // Notify assignees + creator (except actor)
        $userIds = $task->assignments()->pluck('user_id')->push($task->created_by_id)
            ->unique()->reject(fn ($id) => $id === $request->user()->id);
        foreach ($userIds as $uid) {
            AppNotification::notify(
                $uid,
                'task.commented',
                'تعليق جديد على مهمة',
                $task->title,
                ['task_id' => $task->id, 'link' => route('tasks.show', $task)]
            );
        }

        return back()->with('success', 'تم إضافة التعليق');
    }
}
