<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Central key-value configuration surface for SaaS-wide settings that
 * SuperAdmin edits from the Settings page. Everything from Moyasar keys
 * to hero copy flows through here.
 *
 * Cache strategy: one combined "system_settings" cache entry (full
 * key→value map) with a 1-hour TTL. Invalidated on every set/setMany
 * so the admin sees their change immediately after redirect. Reading
 * from Cache first keeps individual lookups O(1) in memory; only
 * read-through to DB when the cache is cold.
 *
 * Secrets (moyasar_secret_key, mail_password) are stored encrypted by
 * the controller that writes them — this model doesn't auto-encrypt
 * because most keys are plain strings and the encryption decision is
 * per-key.
 */
class SystemSetting extends Model
{
    public const CACHE_KEY = 'system_settings';
    public const CACHE_TTL = 3600;

    protected $fillable = ['key', 'value', 'group', 'label'];

    /**
     * Read a setting value, with optional default. Hot path — reads from
     * cache, falls back to DB if cache is cold.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all_cached();

        return $all[$key] ?? $default;
    }

    /**
     * Write a single setting. Creates the row if missing (upsert) and
     * invalidates the combined cache.
     */
    public static function set(string $key, mixed $value, ?string $group = null, ?string $label = null): void
    {
        // `group` is NOT NULL in the schema; default to 'misc' so
        // callers that don't care about tab placement still insert
        // cleanly.
        self::updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                'group' => $group ?? 'misc',
                'label' => $label,
            ], fn ($v) => $v !== null),
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Bulk write from the Settings page. Takes a flat [key => value] map
     * and a default group, applies each and invalidates cache once at
     * the end.
     */
    public static function setMany(array $data, ?string $group = null): void
    {
        foreach ($data as $key => $value) {
            self::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                    'group' => $group,
                ],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Internal: read full settings map through cache. Returns plain
     * [key => value] array so callers don't carry Eloquent overhead.
     *
     * @return array<string, mixed>
     */
    protected static function all_cached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::query()->pluck('value', 'key')->toArray();
        });
    }
}
