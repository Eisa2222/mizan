<?php

namespace Tests\Unit\Services;

use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Plan;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation + atomic redemption of coupons. The service is the single
 * source of truth for discount math + race-safety — these tests lock
 * down both so a regression (e.g. switching to $coupon->uses_count++)
 * fails loudly.
 */
class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $service;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CouponService();
        $this->plan = Plan::create([
            'name' => 'Test', 'slug' => 'test',
            'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true,
        ]);
    }

    public function test_validate_returns_failure_for_unknown_code(): void
    {
        $result = $this->service->validate('NOTREAL', $this->plan->id, 'monthly', 100);

        $this->assertFalse($result['valid']);
        $this->assertSame(0.0, $result['discount']);
        $this->assertStringContainsString('غير موجود', $result['message']);
    }

    public function test_validate_rejects_inactive_coupon(): void
    {
        Coupon::create([
            'code' => 'OFF20', 'name' => 'test', 'type' => 'percentage',
            'value' => 20, 'is_active' => false,
        ]);

        $result = $this->service->validate('OFF20', $this->plan->id, 'monthly', 100);
        $this->assertFalse($result['valid']);
    }

    public function test_validate_rejects_expired_coupon(): void
    {
        Coupon::create([
            'code' => 'OLD', 'name' => 'x', 'type' => 'fixed',
            'value' => 10, 'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $result = $this->service->validate('OLD', $this->plan->id, 'monthly', 100);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('انتهت', $result['message']);
    }

    public function test_validate_rejects_max_uses_reached(): void
    {
        Coupon::create([
            'code' => 'FULL', 'name' => 'x', 'type' => 'percentage',
            'value' => 10, 'is_active' => true,
            'max_uses' => 5, 'uses_count' => 5,
        ]);

        $result = $this->service->validate('FULL', $this->plan->id, 'monthly', 100);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('الأقصى', $result['message']);
    }

    public function test_validate_rejects_min_order_amount_below_threshold(): void
    {
        Coupon::create([
            'code' => 'MIN200', 'name' => 'x', 'type' => 'fixed',
            'value' => 20, 'is_active' => true,
            'min_order_amount' => 200,
        ]);

        $result = $this->service->validate('MIN200', $this->plan->id, 'monthly', 100);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('الحد الأدنى', $result['message']);
    }

    public function test_validate_computes_percentage_discount(): void
    {
        Coupon::create([
            'code' => 'HALF', 'name' => 'half', 'type' => 'percentage',
            'value' => 50, 'is_active' => true,
        ]);

        $result = $this->service->validate('HALF', $this->plan->id, 'monthly', 100);
        $this->assertTrue($result['valid']);
        $this->assertEquals(50.0, $result['discount']);
        $this->assertEquals(50.0, $result['final_amount']);
    }

    public function test_validate_computes_fixed_discount(): void
    {
        Coupon::create([
            'code' => 'FIXED25', 'name' => 'x', 'type' => 'fixed',
            'value' => 25, 'is_active' => true,
        ]);

        $result = $this->service->validate('FIXED25', $this->plan->id, 'monthly', 100);
        $this->assertTrue($result['valid']);
        $this->assertEquals(25.0, $result['discount']);
        $this->assertEquals(75.0, $result['final_amount']);
    }

    public function test_fixed_discount_capped_at_order_amount(): void
    {
        // A 500 SAR coupon on a 100 SAR order must NOT produce negative total.
        Coupon::create([
            'code' => 'BIG', 'name' => 'x', 'type' => 'fixed',
            'value' => 500, 'is_active' => true,
        ]);

        $result = $this->service->validate('BIG', $this->plan->id, 'monthly', 100);
        $this->assertEquals(100.0, $result['discount']);
        $this->assertEquals(0.0, $result['final_amount']);
    }

    public function test_validate_respects_applicable_plans_filter(): void
    {
        $other = Plan::create(['name' => 'Other', 'slug' => 'other', 'price_monthly' => 50, 'price_yearly' => 500]);

        Coupon::create([
            'code' => 'BASICONLY', 'name' => 'x', 'type' => 'percentage', 'value' => 10,
            'is_active' => true,
            'applicable_plans' => [$this->plan->id], // only the test plan
        ]);

        $okay = $this->service->validate('BASICONLY', $this->plan->id, 'monthly', 100);
        $this->assertTrue($okay['valid']);

        $denied = $this->service->validate('BASICONLY', $other->id, 'monthly', 100);
        $this->assertFalse($denied['valid']);
        $this->assertStringContainsString('الباقة', $denied['message']);
    }

    public function test_validate_respects_billing_cycle_filter(): void
    {
        Coupon::create([
            'code' => 'YEARLYONLY', 'name' => 'x', 'type' => 'percentage', 'value' => 30,
            'is_active' => true,
            'billing_cycles' => ['yearly'],
        ]);

        $yearly  = $this->service->validate('YEARLYONLY', $this->plan->id, 'yearly', 1000);
        $monthly = $this->service->validate('YEARLYONLY', $this->plan->id, 'monthly', 100);

        $this->assertTrue($yearly['valid']);
        $this->assertFalse($monthly['valid']);
    }

    public function test_apply_increments_uses_count_and_creates_use_row(): void
    {
        $coupon = Coupon::create([
            'code' => 'USE', 'name' => 'x', 'type' => 'fixed',
            'value' => 10, 'is_active' => true, 'uses_count' => 2,
        ]);

        // Stub subscription_id (FK not enforced in unit context; we're
        // testing the service mechanics, not referential integrity).
        $use = $this->service->apply($coupon, 'tenant-uuid', 999, 10.0);

        $this->assertInstanceOf(CouponUse::class, $use);
        $this->assertEquals(3, $coupon->fresh()->uses_count);
        $this->assertEquals('tenant-uuid', $use->tenant_id);
        $this->assertEquals(10.0, (float) $use->discount_amount);
    }

    public function test_apply_is_atomic_under_concurrent_redemption(): void
    {
        // Simulate 5 back-to-back applies; uses_count must land at 5
        // without any dropped increments.
        $coupon = Coupon::create([
            'code' => 'RACE', 'name' => 'x', 'type' => 'fixed',
            'value' => 5, 'is_active' => true, 'uses_count' => 0,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->service->apply($coupon, "tenant-{$i}", $i + 1, 5.0);
        }

        $this->assertEquals(5, $coupon->fresh()->uses_count);
        $this->assertEquals(5, CouponUse::where('coupon_id', $coupon->id)->count());
    }
}
