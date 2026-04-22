<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\AuditLogger;
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

    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $before = ['status' => $tenant->status];
        $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

        AuditLogger::record('tenant.suspend', $tenant,
            before: $before,
            after:  ['status' => Tenant::STATUS_SUSPENDED],
            reason: $request->string('reason')->toString() ?: null,
        );

        return back()->with('success', "تم تعليق المستأجر «{$tenant->company_name}».");
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $before = ['status' => $tenant->status];
        $tenant->update(['status' => Tenant::STATUS_ACTIVE]);

        AuditLogger::record('tenant.activate', $tenant,
            before: $before,
            after:  ['status' => Tenant::STATUS_ACTIVE],
        );

        return back()->with('success', "تم تفعيل المستأجر «{$tenant->company_name}».");
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        // Log BEFORE the delete so the target_id still resolves in the
        // audit row — stancl fires TenantDeleted pipeline async and the
        // DB may disappear before the insert otherwise.
        AuditLogger::record('tenant.delete', $tenant,
            before: ['status' => $tenant->status, 'company_name' => $tenant->company_name],
        );

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
        $new = Subscription::create([
            'tenant_id'     => $tenant->id,
            'plan_id'       => $plan->id,
            'billing_cycle' => $cycle,
            'status'        => Subscription::STATUS_ACTIVE,
            'starts_at'     => now(),
            'ends_at'       => now()->addMonths($cycle === 'yearly' ? 12 : 1),
            'amount'        => $plan->priceFor($cycle),
            'currency'      => $plan->currency,
        ]);

        AuditLogger::record('tenant.change_plan', $tenant,
            before: ['plan_id' => $current?->plan_id, 'subscription_id' => $current?->id],
            after:  ['plan_id' => $plan->id, 'subscription_id' => $new->id, 'cycle' => $cycle],
        );

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

        $oldEndsAt = $sub->ends_at->copy();
        $sub->update(['ends_at' => $sub->ends_at->addDays($days)]);

        AuditLogger::record('tenant.extend', $tenant,
            before: ['ends_at' => $oldEndsAt->toIso8601String()],
            after:  ['ends_at' => $sub->ends_at->toIso8601String()],
            reason: "+{$days} يوم",
        );

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

        AuditLogger::record('tenant.impersonate', $tenant,
            after: ['user_id' => $userId, 'domain' => $domain->domain],
        );

        $scheme = $request->secure() ? 'https' : 'http';
        $url    = "{$scheme}://{$domain->domain}/impersonate/" . $token->token;

        return redirect()->away($url);
    }
}
