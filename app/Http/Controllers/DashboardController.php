<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\ArticleUpdate;
use App\Models\LegalDocument;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $orgId = $request->user()->org_id;
        $userId = $request->user()->id;

        $documentsCount = LegalDocument::where('org_id', $orgId)->count();
        $documentsByType = LegalDocument::where('org_id', $orgId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $tasksCount = Task::where('org_id', $orgId)->count();
        $tasksByStatus = Task::where('org_id', $orgId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $myOpenTasks = Task::where('org_id', $orgId)
            ->where('created_by_id', $userId)
            ->whereIn('status', [1, 2, 3])
            ->latest()
            ->take(5)
            ->get();

        $overdueTasks = Task::where('org_id', $orgId)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->whereNotIn('status', [4, 5])
            ->count();

        $recentActivities = TaskActivity::with(['task', 'user'])
            ->whereHas('task', fn ($q) => $q->where('org_id', $orgId))
            ->latest()
            ->take(8)
            ->get();

        $recentDocuments = LegalDocument::where('org_id', $orgId)
            ->latest()
            ->take(5)
            ->get();

        // Workflow surfacing (Layer E10) — pull recent article updates and
        // unread notifications onto the dashboard so users see active work
        // without hunting through tabs.
        $recentArticleUpdates = ArticleUpdate::with(['document', 'creator'])
            ->whereHas('document', fn ($q) => $q->where('org_id', $orgId))
            ->latest('update_date')
            ->take(5)
            ->get();

        $unreadNotifications = AppNotification::where('user_id', $userId)
            ->whereNull('read_at')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'documentsCount', 'documentsByType',
            'tasksCount', 'tasksByStatus', 'myOpenTasks', 'overdueTasks',
            'recentActivities', 'recentDocuments',
            'recentArticleUpdates', 'unreadNotifications'
        ));
    }
}
