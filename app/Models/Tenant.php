<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * SaaS tenant — one row per customer organization. Each tenant has a
 * dedicated MySQL/SQLite database (managed by stancl's multi-database
 * tenancy) plus one or more domains/subdomains in the central `domains`
 * table.
 *
 * Custom columns (company_name, owner_*, logo, timezone, language, status,
 * settings) live alongside the base `id`+`data` JSON. Declaring them via
 * getCustomColumns() tells stancl to persist them natively instead of
 * serialising into `data` — that keeps indexes + foreign keys useful
 * and lets SuperAdmin listings filter on status without JSON extraction.
 *
 * Status values mirror the subscription lifecycle:
 *   active     — paying or trialing, full access
 *   suspended  — SuperAdmin-suspended (e.g. TOS violation)
 *   archived   — cancelled + data retained for export grace period
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED  = 'archived';

    /**
     * Native columns (not serialised into `data` JSON).
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'company_name',
            'owner_name',
            'owner_email',
            'owner_phone',
            'logo',
            'timezone',
            'language',
            'status',
            'settings',
        ];
    }

    protected $casts = [
        'data'     => 'array',
        'settings' => 'array',
    ];

    /* ─── Relationships (central DB) ──────────────────────────────────── */

    public function subscriptions()
    {
        return $this->hasMany(\App\Models\Subscription::class, 'tenant_id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(\App\Models\Subscription::class, 'tenant_id')
            ->whereIn('status', ['active', 'trialing'])
            ->latestOfMany();
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class, 'tenant_id');
    }

    /* ─── Status helpers ──────────────────────────────────────────────── */

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
