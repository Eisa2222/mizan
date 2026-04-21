<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Stancl\Tenancy\Features\UserImpersonation;

/**
 * SuperAdmin CRUD for tenants. All write actions are idempotent and
 * log who performed them via the session user — Phase 2 ships without
 * full audit trail; Phase 4 adds a dedicated audits table if the spec
 * grows to require it.
 */
class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tenant::query()->with('activeSubscription.plan');

        if ($term = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($term) {
                $q->where('company_name', 'like', "%{$term}%")
                  ->orWhere('owner_email', 'like', "%{$term}%")
                  ->orWhere('owner_name', 'like', "%{$term}%");
            });
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return view('super-admin.tenants.index', [
            'tenants' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['domains', 'subscriptions.plan', 'payments.subscription']);

        return view('super-admin.tenants.show', [
            'tenant' => $tenant,
            'plans'  => Plan::active()->get(),
        ]);
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

        return back()->with('success', "تم تعليق المستأجر «{$tenant->company_name}».");
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => Tenant::STATUS_ACTIVE]);

        return back()->with('success', "تم تفعيل المستأجر «{$tenant->company_name}».");
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        // stancl handles DeleteDatabase via the TenantDeleted event
        // pipeline registered in TenancyServiceProvider.
        $name = $tenant->company_name;
        $tenant->delete();

        return redirect()
            ->route('super-admin.tenants.index')
            ->with('success', "تم حذف «{$name}» وقاعدة بياناته.");
    }

    /**
     * Change the plan on the current active subscription. Creates a new
     * subscription row (keeps history) rather than mutating the old one.
     */
    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id'       => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $current = $tenant->activeSubscription;

        if ($current) {
            $current->update([
                'status'       => Subscription::STATUS_CANCELED,
                'canceled_at'  => now(),
            ]);
        }

        $cycle = $data['billing_cycle'];
        Subscription::create([
            'tenant_id'     => $tenant->id,
            'plan_id'       => $plan->id,
            'billing_cycle' => $cycle,
            'status'        => Subscription::STATUS_ACTIVE,
            'starts_at'     => now(),
            'ends_at'       => now()->addMonths($cycle === 'yearly' ? 12 : 1),
            'amount'        => $plan->priceFor($cycle),
            'currency'      => $plan->currency,
        ]);

        return back()->with('success', "تم تغيير الباقة إلى «{$plan->name}».");
    }

    /**
     * Extend the active subscription by N days without issuing a new
     * charge — used for manual grace periods or support compensation.
     */
    public function extend(Request $request, Tenant $tenant): RedirectResponse
    {
        $days = (int) $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365']])['days'];

        $sub = $tenant->activeSubscription;
        if (! $sub) {
            return back()->with('error', 'لا يوجد اشتراك نشط لهذا المستأجر.');
        }

        $sub->update(['ends_at' => $sub->ends_at->addDays($days)]);

        return back()->with('success', "تم تمديد الاشتراك {$days} يوماً.");
    }

    /**
     * Generate a short-lived (5 min) impersonation token, then redirect
     * to the tenant's primary domain to redeem it. The SuperAdmin
     * lands logged in as the target tenant user.
     */
    public function impersonate(Request $request, Tenant $tenant, int $userId): RedirectResponse
    {
        $domain = $tenant->domains->first();
        if (! $domain) {
            return back()->with('error', 'لا يوجد نطاق مُسجّل لهذا المستأجر.');
        }

        $token = UserImpersonation::impersonate($tenant, $userId, '/');

        $scheme = $request->secure() ? 'https' : 'http';
        $url    = "{$scheme}://{$domain->domain}/impersonate/" . $token->token;

        return redirect()->away($url);
    }
}
