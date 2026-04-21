<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(Request $request): View
    {
        $query = Coupon::query();

        if ($term = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                  ->orWhere('name', 'like', "%{$term}%");
            });
        }

        if ($type = $request->string('type')->trim()->toString()) {
            $query->where('type', $type);
        }

        if ($status = $request->string('status')->trim()->toString()) {
            match ($status) {
                'active'    => $query->active(),
                'expired'   => $query->whereNotNull('expires_at')->where('expires_at', '<', now()),
                'exhausted' => $query->whereNotNull('max_uses')->whereColumn('uses_count', '>=', 'max_uses'),
                default     => null,
            };
        }

        return view('super-admin.coupons.index', [
            'coupons' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $request->only(['q', 'type', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('super-admin.coupons.form', [
            'coupon' => new Coupon(['type' => Coupon::TYPE_PERCENTAGE, 'is_active' => true]),
            'plans'  => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = auth('super_admin')->id();

        $coupon = Coupon::create($data);

        return redirect()
            ->route('super-admin.coupons.index')
            ->with('success', "تم إنشاء الكوبون «{$coupon->code}».");
    }

    public function show(Coupon $coupon): View
    {
        $coupon->load(['uses.tenant', 'uses.subscription.plan']);

        $totalDiscount = $coupon->uses->sum('discount_amount');
        $avgDiscount   = $coupon->uses->count() > 0 ? round($totalDiscount / $coupon->uses->count(), 2) : 0;

        return view('super-admin.coupons.show', compact('coupon', 'totalDiscount', 'avgDiscount'));
    }

    public function edit(Coupon $coupon): View
    {
        return view('super-admin.coupons.form', [
            'coupon' => $coupon,
            'plans'  => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        // Code cannot change post-creation (redemption audit trail).
        $data = $this->validated($request, $coupon);
        unset($data['code']);

        $coupon->update($data);

        return redirect()
            ->route('super-admin.coupons.index')
            ->with('success', "تم تحديث الكوبون «{$coupon->code}».");
    }

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', $coupon->is_active ? 'تم تفعيل الكوبون.' : 'تم إيقاف الكوبون.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->uses_count > 0) {
            $coupon->update(['is_active' => false]);
            return back()->with('error', 'لا يمكن حذف كوبون استُخدم سابقاً — تم إيقافه بدلاً من الحذف.');
        }

        $coupon->delete();
        return redirect()
            ->route('super-admin.coupons.index')
            ->with('success', 'تم حذف الكوبون.');
    }

    /**
     * Helper for the "توليد تلقائي" button in the form.
     */
    public function generate(): array
    {
        return ['code' => strtoupper(Str::random(8))];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $codeRule = $coupon
            ? ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', "unique:coupons,code,{$coupon->id}"]
            : ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', 'unique:coupons,code'];

        return $request->validate([
            'code'             => $codeRule,
            'name'             => ['required', 'string', 'max:100'],
            'type'             => ['required', 'in:percentage,fixed'],
            'value'            => [
                'required', 'numeric', 'min:0.01',
                'max:' . ($request->input('type') === 'percentage' ? 100 : 999999),
            ],
            'max_uses'         => ['nullable', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'applicable_plans' => ['nullable', 'array'],
            'applicable_plans.*' => ['integer', 'exists:plans,id'],
            'billing_cycles'   => ['nullable', 'array'],
            'billing_cycles.*' => ['in:monthly,yearly'],
            'starts_at'        => ['nullable', 'date'],
            'expires_at'       => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'        => ['sometimes', 'boolean'],
        ]);
    }
}
