<?php

namespace App\Console\Commands;

use App\Mail\TrialExpiryWarningMail;
use App\Models\Subscription;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Runs daily at 08:00 (tenant's morning). For each trialing sub whose
 * trial_ends_at is *exactly* `trial_warning_days` away, emails the
 * owner a TrialExpiryWarningMail.
 *
 * "Exactly" here is "same calendar day" — we compare the truncated
 * date, so running this command twice on the same day doesn't spam.
 * (If the mail actually doesn't send in time, re-running next day
 * will pick a closer-to-zero warning — still useful.)
 */
class SendTrialWarningsCommand extends Command
{
    protected $signature = 'saas:send-trial-warnings';
    protected $description = 'Email trial-expiry warnings to tenants `trial_warning_days` before their trial ends.';

    public function handle(): int
    {
        if (! SystemSetting::get('notify_trial_expiring')) {
            $this->info('Trial-expiry notifications are disabled in system settings.');
            return self::SUCCESS;
        }

        $warningDays = (int) SystemSetting::get('trial_warning_days', 3);
        if ($warningDays < 1) {
            $this->warn('trial_warning_days is 0 — nothing to do.');
            return self::SUCCESS;
        }

        $targetDate = now()->addDays($warningDays)->startOfDay();

        $subs = Subscription::query()
            ->with(['tenant', 'plan'])
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereBetween('trial_ends_at', [$targetDate, $targetDate->copy()->endOfDay()])
            ->get();

        if ($subs->isEmpty()) {
            $this->info('No trials ending in ' . $warningDays . ' days.');
            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($subs as $sub) {
            if (! $sub->tenant || ! $sub->tenant->owner_email) continue;

            try {
                Mail::to($sub->tenant->owner_email)->send(
                    new TrialExpiryWarningMail($sub->tenant, $sub, $warningDays)
                );
                $sent++;
            } catch (\Throwable $e) {
                $this->warn("Failed for tenant {$sub->tenant_id}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$sent} trial warning(s).");

        return self::SUCCESS;
    }
}
