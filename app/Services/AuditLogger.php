<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Single entry point for writing audit rows so every caller gets the
 * same shape (actor resolution, IP/UA sniffing, morphable target).
 *
 * Controllers call:
 *     AuditLogger::record('tenant.suspend', $tenant, before: [...], after: [...], reason: '...');
 *
 * Non-SuperAdmin actors (webhooks, scheduler) pass actorType='system'
 * so the UI can filter human vs automated changes.
 */
class AuditLogger
{
    /**
     * @param string $action   e.g. 'tenant.suspend'
     * @param Model|null $target — polymorphic target; omit for non-object-bound events
     * @param array<string,mixed>|null $before  changed-field snapshot BEFORE the mutation
     * @param array<string,mixed>|null $after   changed-field snapshot AFTER the mutation
     * @param string|null $reason  free-form explanation (shown in the Audit UI)
     * @param string $actorType  'super_admin' | 'system' | 'tenant_user'
     */
    public static function record(
        string $action,
        ?Model $target = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        string $actorType = 'super_admin',
    ): AuditLog {
        return AuditLog::create([
            'super_admin_id' => Auth::guard('super_admin')->id(),
            'actor_type'     => $actorType,
            'action'         => $action,
            'target_type'    => $target?->getMorphClass(),
            'target_id'      => $target?->getKey(),
            'before'         => $before,
            'after'          => $after,
            'reason'         => $reason,
            'ip_address'     => self::currentIp(),
            'user_agent'     => self::currentUa(),
            'created_at'     => now(),
        ]);
    }

    /**
     * Diff helper — returns only the keys whose values differ between
     * two models' attributes, trimmed to a whitelist so we don't log
     * huge blobs (e.g. plan->features JSON).
     *
     * @param array<int,string> $watchFields  fields to diff; empty = all
     * @return array{0: array<string,mixed>, 1: array<string,mixed>}  [before, after]
     */
    public static function diff(Model $original, Model $updated, array $watchFields = []): array
    {
        $origAttrs = $original->getAttributes();
        $newAttrs  = $updated->getAttributes();
        $keys = $watchFields ?: array_unique([...array_keys($origAttrs), ...array_keys($newAttrs)]);

        $before = $after = [];
        foreach ($keys as $k) {
            $o = $origAttrs[$k] ?? null;
            $n = $newAttrs[$k]  ?? null;
            if ($o !== $n) {
                $before[$k] = $o;
                $after[$k]  = $n;
            }
        }

        return [$before, $after];
    }

    private static function currentIp(): ?string
    {
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function currentUa(): ?string
    {
        try {
            $ua = Request::userAgent();
            return is_string($ua) ? mb_substr($ua, 0, 255) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
