<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Billing dashboard rendered inside tenant context. Shows the current
 * subscription status and links out to the central Checkout page for
 * renewals/upgrades (Checkout cannot live on the tenant subdomain —
 * payment redirect-flows require a stable central callback URL that's
 * listed in Moyasar's allowed origins).
 */
class TenantBillingController extends Controller
{
    public function index(): View
    {
        $tenant = tenant();

        $current = Subscription::query()
            ->where('tenant_id', $tenant?->id)
            ->with('plan')
            ->orderByDesc('ends_at')
            ->first();

        return view('tenant.billing.index', [
            'tenant'  => $tenant,
            'current' => $current,
            'plans'   => Plan::active()->with('planFeatures')->get(),
        ]);
    }

    /**
     * Redirect to the central checkout page with the chosen plan. The
     * target URL has to be on the central domain so Moyasar's callback
     * works; we hand off via an external redirect.
     */
    public function renew(Plan $plan): RedirectResponse
    {
        $scheme = config('app.env') === 'local' ? 'http' : 'https';
        $baseHost = parse_url(config('app.url') ?? 'mizaan.local', PHP_URL_HOST) ?: 'mizaan.local';

        return redirect()->away("{$scheme}://{$baseHost}/checkout/{$plan->id}?cycle=monthly");
    }
}
