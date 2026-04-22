<?php

namespace Tests\Feature\SaaS;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks down the audit trail end-to-end: AuditLogger writes canonical
 * rows, controller actions produce them under the hood, and the CSV
 * export streams UTF-8 with a BOM.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = SuperAdmin::create([
            'name' => 'SA Test', 'email' => 'sa@test.local',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);
    }

    public function test_audit_logger_records_action_with_current_super_admin(): void
    {
        Auth::guard('super_admin')->login($this->admin);
        $tenant = $this->makeTenant();

        AuditLogger::record('tenant.suspend', $tenant,
            before: ['status' => 'active'],
            after:  ['status' => 'suspended'],
            reason: 'late payment',
        );

        $log = AuditLog::first();
        $this->assertEquals('tenant.suspend', $log->action);
        $this->assertEquals($this->admin->id, $log->super_admin_id);
        $this->assertEquals(Tenant::class, $log->target_type);
        $this->assertEquals($tenant->id, $log->target_id);
        $this->assertEquals(['status' => 'active'], $log->before);
        $this->assertEquals(['status' => 'suspended'], $log->after);
        $this->assertEquals('late payment', $log->reason);
    }

    public function test_audit_logger_works_without_super_admin_for_system_actors(): void
    {
        AuditLogger::record('webhook.payment.refund', null,
            after: ['moyasar_id' => 'pay_x'],
            actorType: 'system',
        );

        $log = AuditLog::first();
        $this->assertNull($log->super_admin_id);
        $this->assertEquals('system', $log->actor_type);
    }

    public function test_diff_returns_only_changed_fields_within_watchlist(): void
    {
        $original = new Plan(['name' => 'A', 'price_monthly' => 100, 'is_active' => true]);
        $updated  = new Plan(['name' => 'B', 'price_monthly' => 100, 'is_active' => false]);

        [$before, $after] = AuditLogger::diff($original, $updated, ['name', 'price_monthly', 'is_active']);

        $this->assertArrayHasKey('name', $before);
        $this->assertArrayHasKey('is_active', $before);
        $this->assertArrayNotHasKey('price_monthly', $before); // unchanged, excluded
    }

    public function test_suspend_action_creates_audit_row(): void
    {
        Auth::guard('super_admin')->login($this->admin);
        $tenant = $this->makeTenant();

        $this->post(route('super-admin.tenants.suspend', $tenant), ['reason' => 'billing dispute'])
            ->assertRedirect();

        $log = AuditLog::where('action', 'tenant.suspend')->first();
        $this->assertNotNull($log);
        $this->assertEquals('billing dispute', $log->reason);
    }

    public function test_audit_index_page_is_guarded(): void
    {
        $this->get(route('super-admin.audit.index'))->assertRedirect(route('super-admin.login'));
    }

    public function test_audit_index_page_renders_for_super_admin(): void
    {
        Auth::guard('super_admin')->login($this->admin);
        AuditLogger::record('tenant.suspend', $this->makeTenant(), reason: 'x');

        $this->get(route('super-admin.audit.index'))
            ->assertOk()
            ->assertSee('تعليق مستأجر'); // actionLabel() translation
    }

    public function test_audit_csv_export_streams_rows_with_utf8_bom(): void
    {
        Auth::guard('super_admin')->login($this->admin);
        AuditLogger::record('tenant.suspend', $this->makeTenant(), reason: 'للاختبار');

        $response = $this->get(route('super-admin.audit.export'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $body = $response->streamedContent();
        // UTF-8 BOM must come first so Excel reads Arabic correctly
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('tenant.suspend', $body);
        $this->assertStringContainsString('للاختبار', $body);
    }

    public function test_payments_csv_export_includes_all_columns(): void
    {
        Auth::guard('super_admin')->login($this->admin);

        $tenant = $this->makeTenant();
        Payment::create([
            'tenant_id' => $tenant->id,
            'moyasar_payment_id' => 'pay_export_test',
            'amount' => 299, 'currency' => 'SAR',
            'status' => Payment::STATUS_PAID, 'payment_method' => 'creditcard',
            'paid_at' => now(),
        ]);

        $response = $this->get(route('super-admin.payments.export'));
        $response->assertOk();

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('pay_export_test', $body);
        $this->assertStringContainsString('299', $body);
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'id'           => (string) Str::uuid(),
            'company_name' => 'Audit Test Co',
            'owner_name'   => 'Owner',
            'owner_email'  => 'o@audit.test',
            'status'       => Tenant::STATUS_ACTIVE,
        ]);
    }
}
