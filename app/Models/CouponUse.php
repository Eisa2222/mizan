<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUse extends Model
{
    protected $fillable = [
        'coupon_id', 'tenant_id', 'subscription_id',
        'discount_amount', 'used_at',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'used_at'         => 'datetime',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
