<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant key/value configuration. Lives in the tenant DB (not
 * central), so the connection switch in stancl's tenancy bootstrapper
 * routes queries correctly as long as this model is used inside tenant
 * context.
 *
 * Intentionally simpler than SystemSetting (no cache, no groups) — the
 * tenant workspace is single-org, so contention + cache benefits are
 * minimal. Add caching later if a real hot-path emerges.
 */
class TenantSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::where('key', $key)->first();
        return $row?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)],
        );
    }
}
