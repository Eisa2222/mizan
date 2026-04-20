<?php

namespace Modules\Dashboard\Queries;

use App\Models\AppNotification;
use App\Models\ArticleUpdate;
use App\Models\LegalDocument;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

/**
 * Aggregates every widget the main dashboard renders. Returning a single
 * structured array keeps the controller flat and makes it trivial to swap
 * or extend widgets without touching the view.
 */
class DashboardStatsQuery
{
    public function run(User $user): array
    {
        $orgId = $user->org_id;
        $userId = $user->id;

        $unread = $this->unreadNotifications($userId);

        return [
            'documentsCount'           => LegalDocument::query()->where('org_id', $orgId)->count(),
            'documentsByType'          => $this->documentsByType($orgId),
            'tasksCount'               => Task::query()->where('org_id', $orgId)->count(),
            'tasksByStatus'            => $this->tasksByStatus($orgId),
            'myOpenTasks'              => $this->myOpenTasks($orgId, $userId),
            'overdueTasks'             => $this->overdueTasksCount($orgId),
            'recentActivities'         => $this->recentActivities($orgId),
            'recentDocuments'          => $this->recentDocuments($orgId),
            'recentArticleUpdates'     => $this->recentArticleUpdates($orgId),
            'unreadNotifications'      => $unread,
            'unreadNotificationsCount' => $unread->count(),
            'contractReviewsCount'     => $this->kindCount($orgId, LegalDocument::KIND_CONTRACT_REVIEW),
            'memosCount'               => $this->kindCount($orgId, LegalDocument::KIND_MEMO),
            'tenderReviewsCount'       => $this->kindCount($orgId, LegalDocument::KIND_TENDER_REVIEW),
            'tendersCount'             => \App\Models\Tender::query()->where('org_id', $orgId)->count(),
            'uploadsThisWeek'          => LegalDocument::query()
                ->where('org_id', $orgId)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count(),
        ];
    }

    private function kindCount(int $orgId, string $kind): int
    {
        return LegalDocument::query()
            ->where('org_id', $orgId)
            ->where('kind', $kind)
            ->count();
    }

    private function documentsByType(int $orgId): array
    {
        return LegalDocument::query()
            ->where('org_id', $orgId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }

    private function tasksByStatus(int $orgId): array
    {
        return Task::query()
            ->where('org_id', $orgId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    private function myOpenTasks(int $orgId, int $userId)
    {
        return Task::query()
            ->where('org_id', $orgId)
            ->where('created_by_id', $userId)
            ->whereIn('status', [1, 2, 3])
            ->latest()
            ->take(5)
            ->get();
    }

    private function overdueTasksCount(int $orgId): int
    {
        return Task::query()
            ->where('org_id', $orgId)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->whereNotIn('status', [4, 5])
            ->count();
    }

    private function recentActivities(int $orgId)
    {
        return TaskActivity::query()
            ->with(['task', 'user'])
            ->whereHas('task', fn ($q) => $q->where('org_id', $orgId))
            ->latest()
            ->take(8)
            ->get();
    }

    private function recentDocuments(int $orgId)
    {
        return LegalDocument::query()
            ->where('org_id', $orgId)
            ->latest()
            ->take(5)
            ->get();
    }

    private function recentArticleUpdates(int $orgId)
    {
        return ArticleUpdate::query()
            ->with(['document', 'creator'])
            ->whereHas('document', fn ($q) => $q->where('org_id', $orgId))
            ->latest('update_date')
            ->take(5)
            ->get();
    }

    private function unreadNotifications(int $userId)
    {
        return AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->latest()
            ->take(5)
            ->get();
    }
}
