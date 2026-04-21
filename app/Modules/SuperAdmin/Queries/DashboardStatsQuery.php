<?php

namespace Modules\SuperAdmin\Queries;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated numbers for the SuperAdmin dashboard. All queries are
 * central-DB-only and read-only — safe to run every page load (cached
 * for 60s via config since this view is hit continuously).
 */
class DashboardStatsQuery
{
    /**
     * @return array{
     *   tenants: array{total:int, active:int, suspended:int, trialing:int},
     *   revenue: array{total:float, this_month:float, last_month:float},
     *   subscriptions: array{active:int, expiring_soon:int},
     *   revenue_chart: array<int, array{month:string, amount:float}>,
     *   recent_tenants: \Illuminate\Support\Collection,
     * }
     */
    public function run(): array
    {
        return [
            'tenants'        => $this->tenantStats(),
            'revenue'        => $this->revenueStats(),
            'subscriptions'  => $this->subscriptionStats(),
            'revenue_chart'  => $this->revenueChart(),
            'recent_tenants' => Tenant::query()
                ->with('activeSubscription.plan')
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }

    private function tenantStats(): array
    {
        $byStatus = Tenant::query()
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $trialing = Subscription::where('status', Subscription::STATUS_TRIALING)->distinct('tenant_id')->count('tenant_id');

        return [
            'total'     => (int) $byStatus->sum(),
            'active'    => (int) ($byStatus[Tenant::STATUS_ACTIVE] ?? 0),
            'suspended' => (int) ($byStatus[Tenant::STATUS_SUSPENDED] ?? 0),
            'trialing'  => $trialing,
        ];
    }

    private function revenueStats(): array
    {
        $paid = Payment::where('status', Payment::STATUS_PAID);

        return [
            'total'      => (float) (clone $paid)->sum('amount'),
            'this_month' => (float) (clone $paid)->whereBetween('paid_at', [
                now()->startOfMonth(), now()->endOfMonth(),
            ])->sum('amount'),
            'last_month' => (float) (clone $paid)->whereBetween('paid_at', [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ])->sum('amount'),
        ];
    }

    private function subscriptionStats(): array
    {
        return [
            'active'         => Subscription::whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])->count(),
            'expiring_soon'  => Subscription::whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
                ->whereBetween('ends_at', [now(), now()->addDays(7)])
                ->count(),
        ];
    }

    /**
     * Revenue chart: last 12 months bucket. Returns ordered oldest→newest.
     */
    private function revenueChart(): array
    {
        $start = now()->startOfMonth()->subMonths(11);

        $rows = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Payment $p) => $p->paid_at?->format('Y-m'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        $chart = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $key   = $month->format('Y-m');
            $chart[] = [
                'month'  => $month->translatedFormat('F Y'),
                'amount' => (float) ($rows[$key] ?? 0),
            ];
        }

        return $chart;
    }
}
