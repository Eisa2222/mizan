<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatekeeper for tenant routes. Three outcomes:
 *
 *   ✓ active/trialing sub + tenant.status=active  → pass through
 *   ⏸ tenant.status=suspended                     → errors.suspended
 *   ✗ no active sub / ends_at in the past         → errors.subscription-expired
 *
 * Runs AFTER InitializeTenancyByDomainOrSubdomain so `tenant()` is
 * populated. Central routes never hit this middleware.
 *
 * Impersonation sessions bypass the check so SuperAdmin can always
 * investigate a suspended tenant.
 */
class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        // Impersonation sets this flag on the session — SuperAdmin can
        // always inspect a suspended tenant to debug.
        if ($request->session()->get('tenancy_impersonating')) {
            return $next($request);
        }

        // Tenant context must already be initialised by the tenancy
        // middleware; if not, we let the request through — route
        // ordering should have handled this.
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        if ($tenant->isSuspended()) {
            return response()->view('errors.suspended', ['tenant' => $tenant], 403);
        }

        // Look up the latest subscription centrally. Sorted by ends_at DESC
        // so the most generous active window wins even if a cancelled row
        // still lives in the table.
        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->orderByDesc('ends_at')
            ->first();

        if ($subscription === null || ! $subscription->isActive()) {
            return response()->view('errors.subscription-expired', [
                'tenant'       => $tenant,
                'subscription' => $subscription,
            ], 402);
        }

        // Warm the view layer with the active sub so sidebar badges
        // and billing-status pills don't refetch.
        view()->share('currentSubscription', $subscription);

        return $next($request);
    }
}
