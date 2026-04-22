<?php

namespace Tests\Feature\SaaS;

use App\Http\Middleware\CheckSubscription;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Three-branch gate. Rather than spinning up real tenancy (requires
 * stancl DB provisioning per test — too slow), we swap in a stub
 * `tenant()` helper by binding the Tenant instance to the container
 * under the canonical key stancl uses.
 */
class CheckSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private CheckSubscription $middleware;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckSubscription();
        $this->plan = Plan::create([
            'name' => 'Test', 'slug' => 'test',
            'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true,
        ]);
    }

    public function test_passes_through_when_tenant_has_active_subscription(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDays(5), 'ends_at' => now()->addDays(25),
            'amount' => 100,
        ]);
        $this->bindTenant($tenant);

        $response = $this->runMiddleware();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_passes_through_when_tenant_is_trialing(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(7),
            'starts_at' => now(), 'ends_at' => now()->addDays(7),
            'amount' => 0,
        ]);
        $this->bindTenant($tenant);

        $response = $this->runMiddleware();
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_returns_suspended_view_when_tenant_status_is_suspended(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_SUSPENDED);
        $this->bindTenant($tenant);

        $response = $this->runMiddleware();
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('موقوف', $response->getContent());
    }

    public function test_returns_expired_view_when_no_active_subscription(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_ACTIVE);
        // Only an expired subscription in DB
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly', 'status' => Subscription::STATUS_EXPIRED,
            'starts_at' => now()->subMonths(2), 'ends_at' => now()->subDays(3),
            'amount' => 100,
        ]);
        $this->bindTenant($tenant);

        $response = $this->runMiddleware();
        $this->assertEquals(402, $response->getStatusCode());
        $this->assertStringContainsString('انتهى', $response->getContent());
    }

    public function test_impersonation_session_bypasses_suspension(): void
    {
        $tenant = $this->makeTenant(Tenant::STATUS_SUSPENDED);
        $this->bindTenant($tenant);
        Session::put('tenancy_impersonating', true);

        $response = $this->runMiddleware();
        $this->assertEquals(200, $response->getStatusCode());
    }

    private function makeTenant(string $status): Tenant
    {
        return Tenant::create([
            'id'           => (string) Str::uuid(),
            'company_name' => 'Test Co',
            'owner_name'   => 'Owner',
            'owner_email'  => 'o@test.local',
            'status'       => $status,
        ]);
    }

    private function bindTenant(Tenant $tenant): void
    {
        // stancl's tenant() helper resolves Stancl\Tenancy\Contracts\Tenant
        // from the container. Binding our Eloquent model satisfies it for
        // middleware purposes.
        app()->instance(\Stancl\Tenancy\Contracts\Tenant::class, $tenant);
    }

    private function runMiddleware(): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('/whatever', 'GET');
        $request->setLaravelSession(app('session.store'));

        return $this->middleware->handle($request, fn () => response('OK'));
    }
}
