<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs daily at 00:00. Finds subscriptions whose trial has ended and:
 *   1. Marks the subscription status=expired
 *   2. If SystemSetting('trial_suspend_after_expiry')=true, suspends
 *      the tenant row (tenant.status=suspended) so the subscription
 *      middleware (Phase 4) blocks login.
 *
 * Safe to re-run — idempotent on both transitions.
 */
class CheckTrialExpiryCommand extends Command
{
    protected $signature = 'saas:check-trial-expiry';
    protected $description = 'Expire trialing subscriptions whose trial_ends_at is in the past + optionally suspend the tenant.';

    public function handle(): int
    {
        $suspendAfter = (bool) SystemSetting::get('trial_suspend_after_expiry');

        $expired = Subscription::query()
            ->where('status', Subscription::STATUS_TRIALING)
            ->where('trial_ends_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No trials to expire.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($expired, $suspendAfter) {
            foreach ($expired as $sub) {
                $sub->update(['status' => Subscription::STATUS_EXPIRED]);

                if ($suspendAfter) {
                    Tenant::where('id', $sub->tenant_id)->update([
                        'status' => Tenant::STATUS_SUSPENDED,
                    ]);
                }
            }
        });

        $this->info("Expired {$expired->count()} trial(s)" . ($suspendAfter ? ' + suspended tenants.' : '.'));

        return self::SUCCESS;
    }
}
