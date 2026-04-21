<?php

namespace App\Http\Controllers;

use App\Jobs\CreateTenantJob;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Services\CouponService;
use App\Services\MoyasarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public checkout flow. Four endpoints:
 *
 *   show()        — render the checkout page with the chosen plan
 *   applyCoupon() — AJAX coupon validation (no side effects)
 *   callback()    — Moyasar redirects here after payment; we verify via
 *                   the server-side API, persist the Tenant +
 *                   Subscription + Payment, dispatch CreateTenantJob,
 *                   then redirect to success()
 *   success()     — static "thanks, check your email" page
 *
 * Everything runs on central domains only. Tenant DB provisioning is
 * queued (CreateTenantJob) so the 200 response ships before the slow
 * migrate + seed finish.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly MoyasarService $moyasar,
        private readonly CouponService $coupons,
    ) {}

    public function show(Plan $plan, Request $request): View
    {
        abort_unless($plan->is_active, 404);

        $cycle = in_array($request->string('cycle')->toString(), ['monthly', 'yearly'], true)
            ? $request->string('cycle')->toString()
            : 'monthly';

        return view('checkout.show', [
            'plan'      => $plan,
            'cycle'     => $cycle,
            'amount'    => $plan->priceFor($cycle),
            'settings'  => [
                'moyasar_pk'          => (string) config('services.moyasar.publishable_key'),
                'moyasar_methods'     => (array) (SystemSetting::get('moyasar_enabled_methods') ?: ['creditcard']),
                'test_mode'           => (bool) (config('services.moyasar.test_mode') ?? SystemSetting::get('moyasar_test_mode')),
                'trial_enabled'       => (bool) SystemSetting::get('trial_enabled'),
                'trial_days'          => (int) SystemSetting::get('trial_days', $plan->trial_days),
                'trial_requires_payment' => (bool) SystemSetting::get('trial_requires_payment'),
            ],
        ]);
    }

    /**
     * AJAX: validate a coupon code for this cart. Never throws — always
     * returns JSON the form can render inline.
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'          => ['required', 'string', 'max:64'],
            'plan_id'       => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'amount'        => ['required', 'numeric', 'min:0'],
        ]);

        $result = $this->coupons->validate(
            $data['code'],
            (int) $data['plan_id'],
            $data['billing_cycle'],
            (float) $data['amount'],
        );

        return response()->json([
            'valid'        => $result['valid'],
            'discount'     => $result['discount'],
            'final_amount' => $result['final_amount'],
            'message'      => $result['message'],
        ]);
    }

    /**
     * Callback handler — Moyasar redirects the browser here after the
     * payment form. We trust NOTHING from the query string; the
     * payment_id is the only useful param, everything else is verified
     * via `MoyasarService::getPayment()`.
     */
    public function callback(Request $request): RedirectResponse
    {
        $paymentId = (string) $request->query('id', '');
        if ($paymentId === '') {
            return redirect()->route('landing')->with('error', 'رابط الدفع غير صالح.');
        }

        try {
            $mp = $this->moyasar->getPayment($paymentId);
        } catch (\Throwable $e) {
            Log::error('Moyasar getPayment failed', ['id' => $paymentId, 'error' => $e->getMessage()]);
            return redirect()->route('landing')->with('error', 'تعذّر التحقق من عملية الدفع. حاول مرة أخرى.');
        }

        $metadata = is_array($mp['metadata'] ?? null) ? $mp['metadata'] : [];

        // Nothing persists until Moyasar confirms `status=paid` OR the
        // flow is a trial that doesn't require payment.
        if (($mp['status'] ?? null) !== 'paid') {
            $this->recordFailedPayment($paymentId, $mp, $metadata);
            return redirect()->route('landing')->with('error', $mp['source']['message'] ?? 'فشلت عملية الدفع.');
        }

        return DB::transaction(function () use ($paymentId, $mp, $metadata) {
            $plan = Plan::findOrFail((int) ($metadata['plan_id'] ?? 0));
            $cycle = in_array($metadata['billing_cycle'] ?? '', ['monthly', 'yearly'], true)
                ? $metadata['billing_cycle'] : 'monthly';

            // 1) Tenant shell (DB created async by CreateTenantJob).
            $tenant = Tenant::create([
                'id'            => (string) Str::uuid(),
                'company_name'  => (string) ($metadata['company_name'] ?? 'New Company'),
                'owner_name'    => (string) ($metadata['owner_name']   ?? ''),
                'owner_email'   => (string) ($metadata['owner_email']  ?? ''),
                'owner_phone'   => (string) ($metadata['owner_phone']  ?? '') ?: null,
                'status'        => Tenant::STATUS_ACTIVE,
                'timezone'      => (string) SystemSetting::get('default_timezone', 'Asia/Riyadh'),
                'language'      => (string) SystemSetting::get('default_language', 'ar'),
            ]);

            // 2) Subscription + discount.
            $amount = (float) ($mp['amount'] / 100);
            $discount = (float) ($metadata['discount_amount'] ?? 0);

            $coupon = null;
            if (! empty($metadata['coupon_code'])) {
                $coupon = Coupon::where('code', $metadata['coupon_code'])->first();
            }

            $trialDays = $this->effectiveTrialDays($plan);
            $sub = Subscription::create([
                'tenant_id'      => $tenant->id,
                'plan_id'        => $plan->id,
                'billing_cycle'  => $cycle,
                'status'         => $trialDays > 0 ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE,
                'trial_ends_at'  => $trialDays > 0 ? now()->addDays($trialDays) : null,
                'starts_at'      => now(),
                'ends_at'        => $trialDays > 0
                    ? now()->addDays($trialDays)
                    : now()->addMonths($cycle === 'yearly' ? 12 : 1),
                'amount'         => $amount,
                'currency'       => $mp['currency'] ?? 'SAR',
                'coupon_id'      => $coupon?->id,
                'discount_amount'=> $discount,
                'metadata'       => ['moyasar_payment_id' => $paymentId],
            ]);

            // 3) Payment row.
            Payment::create([
                'tenant_id'          => $tenant->id,
                'subscription_id'    => $sub->id,
                'moyasar_payment_id' => $paymentId,
                'moyasar_invoice_id' => $mp['invoice_id'] ?? null,
                'amount'             => $amount,
                'currency'           => $mp['currency'] ?? 'SAR',
                'status'             => Payment::STATUS_PAID,
                'payment_method'     => $mp['source']['type'] ?? null,
                'moyasar_response'   => $mp,
                'paid_at'            => now(),
            ]);

            // 4) Consume coupon ATOMICALLY (race-safe increment).
            if ($coupon !== null) {
                $this->coupons->apply($coupon, $tenant->id, $sub->id, $discount);
            }

            // 5) Provision DB + admin user + welcome mail (queued).
            CreateTenantJob::dispatch($tenant, $sub);

            return redirect()->route('checkout.success', ['tenant' => $tenant->id]);
        });
    }

    public function success(Request $request): View
    {
        $tenant = Tenant::find($request->query('tenant'));

        return view('checkout.success', ['tenant' => $tenant]);
    }

    private function effectiveTrialDays(Plan $plan): int
    {
        if (! SystemSetting::get('trial_enabled')) {
            return 0;
        }

        return (int) SystemSetting::get('trial_days', $plan->trial_days);
    }

    private function recordFailedPayment(string $paymentId, array $mp, array $metadata): void
    {
        // Failed payments before tenant creation have no tenant_id to
        // reference — the FK on payments.tenant_id would reject the
        // insert. Log for support follow-up; the user can retry with a
        // fresh Moyasar payment.
        Log::warning('Checkout payment failed before tenant provisioning', [
            'moyasar_payment_id' => $paymentId,
            'status'             => $mp['status'] ?? null,
            'message'            => $mp['source']['message'] ?? null,
            'metadata'           => $metadata,
        ]);
    }
}
