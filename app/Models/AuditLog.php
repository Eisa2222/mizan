<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false; // only created_at (set by migration default)

    protected $fillable = [
        'super_admin_id', 'actor_type',
        'action', 'target_type', 'target_id',
        'before', 'after', 'reason',
        'ip_address', 'user_agent',
        'created_at',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    /**
     * Polymorphic target (Tenant / Payment / Plan / Coupon / ...).
     * Not using Laravel's native morphTo() because our class uses
     * string target_id (UUIDs for tenants, ints elsewhere) — MorphTo
     * would enforce a single type per query.
     */
    public function target()
    {
        if (! $this->target_type || ! class_exists($this->target_type)) {
            return null;
        }

        return $this->target_type::find($this->target_id);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'tenant.suspend'       => 'تعليق مستأجر',
            'tenant.activate'      => 'تفعيل مستأجر',
            'tenant.delete'        => 'حذف مستأجر',
            'tenant.impersonate'   => 'انتحال شخصية',
            'tenant.change_plan'   => 'تغيير باقة',
            'tenant.extend'        => 'تمديد اشتراك',
            'subscription.cancel'  => 'إلغاء اشتراك',
            'payment.refund'       => 'استرجاع دفعة',
            'plan.create'          => 'إنشاء باقة',
            'plan.update'          => 'تعديل باقة',
            'plan.toggle'          => 'تبديل حالة باقة',
            'coupon.create'        => 'إنشاء كوبون',
            'coupon.update'        => 'تعديل كوبون',
            'coupon.toggle'        => 'تبديل حالة كوبون',
            'settings.update'      => 'تحديث الإعدادات',
            default                => $this->action,
        };
    }
}
