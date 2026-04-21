<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Discount code applied at checkout. All validation predicates are
 * pure methods so CouponService can short-circuit with clear reasons.
 *
 * `calculateDiscount()` returns the discount AMOUNT, not the final
 * amount. Callers subtract it from the order total themselves — keeps
 * percentage vs fixed behaviour transparent in the call site.
 */
class Coupon extends Model
{
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED      = 'fixed';

    protected $fillable = [
        'code', 'name', 'type', 'value',
        'max_uses', 'uses_count',
        'min_order_amount',
        'applicable_plans', 'billing_cycles',
        'starts_at', 'expires_at',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'value'            => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'applicable_plans' => 'array',
        'billing_cycles'   => 'array',
        'starts_at'        => 'datetime',
        'expires_at'       => 'datetime',
        'is_active'        => 'boolean',
    ];

    /* ─── Relations ───────────────────────────────────────────────────── */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by');
    }

    public function uses(): HasMany
    {
        return $this->hasMany(CouponUse::class);
    }

    /* ─── Scopes ──────────────────────────────────────────────────────── */

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
            });
    }

    /* ─── Predicates ──────────────────────────────────────────────────── */

    public function isValid(): bool
    {
        if (! $this->is_active) return false;
        if ($this->isExpired()) return false;
        if ($this->hasReachedMaxUses()) return false;
        if ($this->starts_at !== null && $this->starts_at->isFuture()) return false;

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasReachedMaxUses(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }

    public function isApplicableToPlan(int $planId): bool
    {
        // Null applicable_plans = applies to every plan.
        return empty($this->applicable_plans) || in_array($planId, $this->applicable_plans, true);
    }

    public function isApplicableToBillingCycle(string $cycle): bool
    {
        return empty($this->billing_cycles) || in_array($cycle, $this->billing_cycles, true);
    }

    /**
     * Compute discount amount (NOT final price) for the given order total.
     * Percentage coupons are capped at the order amount so a 200% coupon
     * can't produce a negative final price.
     */
    public function calculateDiscount(float $amount): float
    {
        if ($this->type === self::TYPE_PERCENTAGE) {
            $discount = round($amount * ((float) $this->value / 100), 2);
        } else {
            $discount = (float) $this->value;
        }

        return (float) min($discount, $amount);
    }

    public function getRemainingUses(): ?int
    {
        if ($this->max_uses === null) return null;

        return max(0, $this->max_uses - $this->uses_count);
    }
}
