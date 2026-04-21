<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUse;
use Illuminate\Support\Facades\DB;

/**
 * Coupon validation + application. All validation returns a structured
 * array (never throws for invalid coupons) so the checkout AJAX handler
 * can render the error message directly without try/catch noise.
 *
 * Atomicity note: apply() increments uses_count via DB::increment()
 * inside the same transaction as the insert. This prevents race
 * conditions when two checkouts redeem the last use of a capped coupon
 * simultaneously — the increment is atomic at the row level.
 */
class CouponService
{
    /**
     * Validate a coupon against order context. Does NOT consume the
     * coupon — that happens in apply() after payment succeeds.
     *
     * @return array{
     *   valid: bool,
     *   coupon: ?\App\Models\Coupon,
     *   discount: float,
     *   final_amount: float,
     *   message: string,
     * }
     */
    public function validate(string $code, int $planId, string $billingCycle, float $amount): array
    {
        $coupon = Coupon::where('code', $code)->first();

        if ($coupon === null) {
            return $this->failure($amount, 'هذا الكود غير موجود.');
        }

        if (! $coupon->is_active) {
            return $this->failure($amount, 'هذا الكوبون غير مفعّل.');
        }

        if ($coupon->starts_at !== null && $coupon->starts_at->isFuture()) {
            return $this->failure($amount, 'هذا الكوبون لم يبدأ بعد.');
        }

        if ($coupon->isExpired()) {
            return $this->failure($amount, 'انتهت صلاحية هذا الكوبون.');
        }

        if ($coupon->hasReachedMaxUses()) {
            return $this->failure($amount, 'تجاوز هذا الكوبون الحد الأقصى للاستخدامات.');
        }

        if (! $coupon->isApplicableToPlan($planId)) {
            return $this->failure($amount, 'هذا الكوبون لا ينطبق على الباقة المختارة.');
        }

        if (! $coupon->isApplicableToBillingCycle($billingCycle)) {
            return $this->failure(
                $amount,
                $billingCycle === 'yearly'
                    ? 'هذا الكوبون لا ينطبق على الدورة السنوية.'
                    : 'هذا الكوبون لا ينطبق على الدورة الشهرية.'
            );
        }

        if ($coupon->min_order_amount !== null && $amount < (float) $coupon->min_order_amount) {
            $min = number_format((float) $coupon->min_order_amount, 2);
            return $this->failure($amount, "الحد الأدنى للطلب هو {$min} ريال.");
        }

        $discount    = $coupon->calculateDiscount($amount);
        $final       = round(max(0, $amount - $discount), 2);
        $savingsText = number_format($discount, 2);

        return [
            'valid'        => true,
            'coupon'       => $coupon,
            'discount'     => $discount,
            'final_amount' => $final,
            'message'      => "تم تطبيق الخصم بنجاح! وفّرت {$savingsText} ريال.",
        ];
    }

    /**
     * Record a redemption atomically:
     *   1) insert coupon_uses row
     *   2) increment coupons.uses_count
     * Both inside a DB transaction so a failure of either rolls back
     * the whole redemption.
     */
    public function apply(Coupon $coupon, string $tenantId, int $subscriptionId, float $discountAmount): CouponUse
    {
        return DB::transaction(function () use ($coupon, $tenantId, $subscriptionId, $discountAmount) {
            $use = CouponUse::create([
                'coupon_id'       => $coupon->id,
                'tenant_id'       => $tenantId,
                'subscription_id' => $subscriptionId,
                'discount_amount' => $discountAmount,
                'used_at'         => now(),
            ]);

            // Atomic DB-level increment — do NOT $coupon->uses_count++ + save().
            Coupon::where('id', $coupon->id)->increment('uses_count');

            return $use;
        });
    }

    private function failure(float $amount, string $message): array
    {
        return [
            'valid'        => false,
            'coupon'       => null,
            'discount'     => 0.0,
            'final_amount' => $amount,
            'message'      => $message,
        ];
    }
}
