<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * SaaS operator account — logs into the `super_admin` guard to manage
 * tenants, plans, coupons, landing CMS, and reports. Distinct from
 * tenant `User` in every way: different table, different guard,
 * different session cookie, different password broker.
 *
 * Phase 1 ships only the auth skeleton. Phase 2 layers the CRUD UI,
 * login flow, and two-factor on top of this model.
 */
class SuperAdmin extends Authenticatable
{
    use Notifiable;

    protected $table = 'super_admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
