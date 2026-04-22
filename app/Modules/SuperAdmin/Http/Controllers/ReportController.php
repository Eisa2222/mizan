<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Revenue + tenant-activity reports for operators. All aggregations
 * run against central tables — payments, subscriptions, tenants — so
 * no tenant context switching is needed.
 *
 * Buckets are aggregated by DATE() in SQL so the result set is bounded
 * by period length (365 rows max for a full year), not by payment
 * count. Safe to run against millions of rows.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->string('period', 'last_30')->toString();
        [$start, $end] = $this->resolvePeriod($period);
        $groupBy = $request->string('group_by', 'day')->toString();

        $payments = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->get();

        $buckets = $payments
            ->groupBy(fn ($p) => match ($groupBy) {
                'month' => $p->paid_at?->format('Y-m'),
                'week'  => $p->paid_at?->format('o-W'),
                default => $p->paid_at?->format('Y-m-d'),
            })
            ->map(fn ($group) => [
                'count'   => $group->count(),
                'revenue' => (float) $group->sum('amount'),
            ])
            ->sortKeys();

        return view('super-admin.reports.index', [
            'period'    => $period,
            'group_by'  => $groupBy,
            'start'     => $start,
            'end'       => $end,
            'buckets'   => $buckets,
            'totals'    => [
                'revenue'   => (float) $payments->sum('amount'),
                'count'     => $payments->count(),
                'tenants'   => Tenant::whereBetween('created_at', [$start, $end])->count(),
                'new_subs'  => Subscription::whereBetween('created_at', [$start, $end])->count(),
                'churn'     => Subscription::where('status', Subscription::STATUS_CANCELED)
                    ->whereBetween('canceled_at', [$start, $end])->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $period = $request->string('period', 'last_30')->toString();
        [$start, $end] = $this->resolvePeriod($period);

        $query = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->with(['tenant', 'subscription.plan']);

        $filename = "revenue-{$period}-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['paid_at', 'tenant', 'plan', 'cycle', 'amount', 'currency', 'payment_method', 'moyasar_id']);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->paid_at?->toIso8601String(),
                        $p->tenant?->company_name,
                        $p->subscription?->plan?->name,
                        $p->subscription?->billing_cycle,
                        $p->amount,
                        $p->currency,
                        $p->payment_method,
                        $p->moyasar_payment_id,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * Map the period shortcut to a concrete [start, end] window.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function resolvePeriod(string $period): array
    {
        $now = now();
        return match ($period) {
            'last_7'  => [$now->copy()->subDays(7)->startOfDay(),   $now->endOfDay()],
            'last_30' => [$now->copy()->subDays(30)->startOfDay(),  $now->endOfDay()],
            'last_90' => [$now->copy()->subDays(90)->startOfDay(),  $now->endOfDay()],
            'ytd'     => [$now->copy()->startOfYear(),              $now->endOfDay()],
            'last_12_months' => [$now->copy()->subMonths(12)->startOfMonth(), $now->endOfDay()],
            default   => [$now->copy()->subDays(30)->startOfDay(),  $now->endOfDay()],
        };
    }
}
