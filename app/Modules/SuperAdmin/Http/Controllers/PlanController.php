<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('super-admin.plans.index', [
            'plans' => Plan::withCount('subscriptions')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.plans.form', ['plan' => new Plan()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::create($this->validated($request));

        AuditLogger::record('plan.create', $plan,
            after: ['name' => $plan->name, 'slug' => $plan->slug, 'price_monthly' => $plan->price_monthly],
        );

        return redirect()
            ->route('super-admin.plans.index')
            ->with('success', "تم إنشاء الباقة «{$plan->name}».");
    }

    public function edit(Plan $plan): View
    {
        return view('super-admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $original = $plan->replicate();
        $plan->update($this->validated($request, $plan));

        [$before, $after] = AuditLogger::diff($original, $plan,
            ['name', 'price_monthly', 'price_yearly', 'is_active', 'max_users', 'max_storage_gb']);
        AuditLogger::record('plan.update', $plan, before: $before, after: $after);

        return redirect()
            ->route('super-admin.plans.index')
            ->with('success', "تم تحديث «{$plan->name}».");
    }

    public function toggle(Plan $plan): RedirectResponse
    {
        $before = $plan->is_active;
        $plan->update(['is_active' => ! $before]);

        AuditLogger::record('plan.toggle', $plan,
            before: ['is_active' => $before],
            after:  ['is_active' => $plan->is_active],
        );

        return back()->with('success', $plan->is_active ? 'تم تفعيل الباقة.' : 'تم إيقاف الباقة.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            // Don't orphan live subscriptions — soft-disable instead.
            $plan->update(['is_active' => false]);
            return back()->with('error', 'لا يمكن حذف الباقة لأنها مرتبطة باشتراكات. تم إيقافها بدلاً من الحذف.');
        }

        $plan->delete();
        return redirect()
            ->route('super-admin.plans.index')
            ->with('success', 'تم حذف الباقة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $slugRule = $plan
            ? ['required', 'string', 'max:64', "unique:plans,slug,{$plan->id}"]
            : ['required', 'string', 'max:64', 'unique:plans,slug'];

        return $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'slug'           => $slugRule,
            'description'    => ['nullable', 'string', 'max:500'],
            'price_monthly'  => ['required', 'numeric', 'min:0'],
            'price_yearly'   => ['required', 'numeric', 'min:0'],
            'currency'       => ['required', 'string', 'size:3'],
            'trial_days'     => ['required', 'integer', 'min:0', 'max:365'],
            'max_users'      => ['required', 'integer', 'min:0'],
            'max_storage_gb' => ['required', 'integer', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
            'is_featured'    => ['sometimes', 'boolean'],
            'sort_order'     => ['nullable', 'integer', 'min:0', 'max:1000'],
            'badge_text'     => ['nullable', 'string', 'max:32'],
            'badge_color'    => ['nullable', 'string', 'max:16'],
        ]);
    }
}
