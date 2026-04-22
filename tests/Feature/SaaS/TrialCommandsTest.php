<?php

namespace Tests\Feature\SaaS;

use App\Mail\TrialExpiryWarningMail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Daily scheduled commands that gate trial lifecycle. These must be
 * idempotent (re-running should not double-process) and must respect
 * SystemSetting toggles so SuperAdmin can pause them mid-flight.
 */
class TrialCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = Plan::create([
            'name' => 'Trial', 'slug' => 'trial',
            'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true,
        ]);
    }

    public function test_check_trial_expiry_marks_expired_subs_as_expired(): void
    {
        SystemSetting::set('trial_suspend_after_expiry', '0', 'trial');

        $tenant = $this->makeTenant();
        $sub = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subHours(2),
            'starts_at' => now()->subDays(15), 'ends_at' => now()->subHours(2),
            'amount' => 0,
        ]);

        $this->artisan('saas:check-trial-expiry')->assertSuccessful();

        $this->assertEquals(Subscription::STATUS_EXPIRED, $sub->fresh()->status);
        $this->assertEquals(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
    }

    public function test_check_trial_expiry_suspends_tenant_when_setting_is_on(): void
    {
        SystemSetting::set('trial_suspend_after_expiry', '1', 'trial');

        $tenant = $this->makeTenant();
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subHours(2),
            'starts_at' => now()->subDays(15), 'ends_at' => now()->subHours(2),
            'amount' => 0,
        ]);

        $this->artisan('saas:check-trial-expiry')->assertSuccessful();

        $this->assertEquals(Tenant::STATUS_SUSPENDED, $tenant->fresh()->status);
    }

    public function test_check_trial_expiry_ignores_future_trials(): void
    {
        $tenant = $this->makeTenant();
        $sub = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(5),
            'starts_at' => now()->subDays(1), 'ends_at' => now()->addDays(5),
            'amount' => 0,
        ]);

        $this->artisan('saas:check-trial-expiry')->assertSuccessful();

        $this->assertEquals(Subscription::STATUS_TRIALING, $sub->fresh()->status);
    }

    public function test_check_trial_expiry_is_idempotent(): void
    {
        // Running twice should not double-apply (no state to corrupt
        // since expired→expired is a no-op, but we verify it doesn't
        // crash or change anything the second time).
        SystemSetting::set('trial_suspend_after_expiry', '0', 'trial');
        $tenant = $this->makeTenant();
        $sub = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->subHours(1),
            'starts_at' => now()->subDays(14), 'ends_at' => now()->subHours(1),
            'amount' => 0,
        ]);

        $this->artisan('saas:check-trial-expiry')->assertSuccessful();
        $this->artisan('saas:check-trial-expiry')->assertSuccessful();

        $this->assertEquals(Subscription::STATUS_EXPIRED, $sub->fresh()->status);
    }

    public function test_send_trial_warnings_emails_tenants_N_days_before_expiry(): void
    {
        Mail::fake();
        SystemSetting::set('notify_trial_expiring', '1', 'notifications');
        SystemSetting::set('trial_warning_days', '3', 'trial');

        $tenant = $this->makeTenant();
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(3)->startOfDay()->addHours(12),
            'starts_at' => now()->subDays(11), 'ends_at' => now()->addDays(3)->startOfDay()->addHours(12),
            'amount' => 0,
        ]);

        $this->artisan('saas:send-trial-warnings')->assertSuccessful();

        Mail::assertSent(TrialExpiryWarningMail::class, fn ($mail) =>
            $mail->hasTo($tenant->owner_email) && $mail->daysRemaining === 3
        );
    }

    public function test_send_trial_warnings_skips_when_disabled_in_settings(): void
    {
        Mail::fake();
        SystemSetting::set('notify_trial_expiring', '0', 'notifications');

        $tenant = $this->makeTenant();
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(3),
            'starts_at' => now()->subDays(11), 'ends_at' => now()->addDays(3),
            'amount' => 0,
        ]);

        $this->artisan('saas:send-trial-warnings')->assertSuccessful();

        Mail::assertNothingSent();
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'id'           => (string) Str::uuid(),
            'company_name' => 'Trial Co',
            'owner_name'   => 'Owner',
            'owner_email'  => 'o@trial.test',
            'status'       => Tenant::STATUS_ACTIVE,
        ]);
    }
}
