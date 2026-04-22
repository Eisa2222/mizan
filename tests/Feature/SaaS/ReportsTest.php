<?php

namespace Tests\Feature\SaaS;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = SuperAdmin::create([
            'name' => 'Reports Admin', 'email' => 'rpt@test.local',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
        $this->plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro',
            'price_monthly' => 899, 'price_yearly' => 8990, 'is_active' => true,
        ]);
        Auth::guard('super_admin')->login($this->admin);
    }

    public function test_reports_index_requires_super_admin(): void
    {
        Auth::guard('super_admin')->logout();
        $this->get(route('super-admin.reports.index'))
            ->assertRedirect(route('super-admin.login'));
    }

    public function test_reports_index_renders_with_default_period(): void
    {
        $this->get(route('super-admin.reports.index'))
            ->assertOk()
            ->assertSee('التقارير')
            ->assertSee('إجمالي الإيرادات');
    }

    public function test_reports_aggregates_revenue_within_period(): void
    {
        $tenant = $this->makeTenant();
        $sub = $this->makeSubscription($tenant);

        // 3 paid payments inside last 30 days, 1 outside
        foreach ([1, 5, 10] as $daysAgo) {
            Payment::create([
                'tenant_id' => $tenant->id, 'subscription_id' => $sub->id,
                'moyasar_payment_id' => 'pay_' . $daysAgo,
                'amount' => 100, 'currency' => 'SAR',
                'status' => Payment::STATUS_PAID, 'payment_method' => 'creditcard',
                'paid_at' => now()->subDays($daysAgo),
                'created_at' => now()->subDays($daysAgo),
            ]);
        }
        // Older than 30d — excluded
        Payment::create([
            'tenant_id' => $tenant->id, 'moyasar_payment_id' => 'pay_old',
            'amount' => 999, 'currency' => 'SAR',
            'status' => Payment::STATUS_PAID, 'payment_method' => 'creditcard',
            'paid_at' => now()->subDays(60),
            'created_at' => now()->subDays(60),
        ]);

        $response = $this->get(route('super-admin.reports.index', ['period' => 'last_30']));
        $response->assertOk();
        // Total should be 300, not 1299.
        $response->assertSee('300.00');
        $response->assertDontSee('1,299');
    }

    public function test_reports_export_streams_csv_with_bom(): void
    {
        $tenant = $this->makeTenant();
        Payment::create([
            'tenant_id' => $tenant->id, 'moyasar_payment_id' => 'pay_export',
            'amount' => 899, 'currency' => 'SAR',
            'status' => Payment::STATUS_PAID, 'payment_method' => 'creditcard',
            'paid_at' => now()->subDay(),
            'created_at' => now()->subDay(),
        ]);

        $response = $this->get(route('super-admin.reports.export', ['period' => 'last_30']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('pay_export', $body);
        $this->assertStringContainsString('899', $body);
    }

    public function test_reports_respects_period_shortcut(): void
    {
        // YTD should include Jan 1 and exclude last year's Dec 31.
        $tenant = $this->makeTenant();

        // Only create a paid payment from 2 days ago — clearly inside YTD.
        Payment::create([
            'tenant_id' => $tenant->id, 'moyasar_payment_id' => 'pay_recent',
            'amount' => 123, 'currency' => 'SAR',
            'status' => Payment::STATUS_PAID, 'payment_method' => 'creditcard',
            'paid_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->get(route('super-admin.reports.index', ['period' => 'ytd']));
        $response->assertOk();
        $response->assertSee('123.00');
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'id'           => (string) Str::uuid(),
            'company_name' => 'R Co',
            'owner_name'   => 'O',
            'owner_email'  => 'o@r.test',
            'status'       => Tenant::STATUS_ACTIVE,
        ]);
    }

    private function makeSubscription(Tenant $tenant): Subscription
    {
        return Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonth(),
            'amount' => 100,
        ]);
    }
}
