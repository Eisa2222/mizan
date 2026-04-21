<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Subscription plan — the thing tenants pick on the landing pricing grid.
 * Prices are stored in major currency units (SAR whole riyals with 2dp)
 * and converted to halalas (× 100) only when passed to Moyasar.
 *
 * `yearlySavingsPercent` is a display-only accessor for the landing page
 * toggle — don't persist the percentage since it derives from the two
 * prices and changes whenever either is edited.
 */
class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'price_monthly', 'price_yearly', 'currency',
        'trial_days', 'max_users', 'max_storage_gb',
        'features', 'limits',
        'is_active', 'is_featured', 'sort_order',
        'badge_text', 'badge_color',
    ];

    protected $casts = [
        'features'       => 'array',
        'limits'         => 'array',
        'is_active'      => 'boolean',
        'is_featured'    => 'boolean',
        'price_monthly'  => 'decimal:2',
        'price_yearly'   => 'decimal:2',
    ];

    /* ─── Relations ───────────────────────────────────────────────────── */

    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class)->orderBy('sort_order');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /* ─── Scopes ──────────────────────────────────────────────────────── */

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }

    /* ─── Accessors ───────────────────────────────────────────────────── */

    public function getFormattedMonthlyAttribute(): string
    {
        return number_format((float) $this->price_monthly, 2) . ' ' . $this->currency;
    }

    public function getFormattedYearlyAttribute(): string
    {
        return number_format((float) $this->price_yearly, 2) . ' ' . $this->currency;
    }

    /**
     * How much % the yearly plan saves vs paying monthly 12×.
     * Returns 0 if either price is zero (free/invalid plan).
     */
    public function getYearlySavingsPercentAttribute(): int
    {
        $monthlyTotal = (float) $this->price_monthly * 12;
        $yearlyPrice  = (float) $this->price_yearly;

        if ($monthlyTotal <= 0 || $yearlyPrice <= 0 || $yearlyPrice >= $monthlyTotal) {
            return 0;
        }

        return (int) round((($monthlyTotal - $yearlyPrice) / $monthlyTotal) * 100);
    }

    public function priceFor(string $cycle): float
    {
        return (float) ($cycle === 'yearly' ? $this->price_yearly : $this->price_monthly);
    }
}
