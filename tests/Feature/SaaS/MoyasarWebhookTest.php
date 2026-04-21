<?php

namespace Tests\Feature\SaaS;

use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The webhook endpoint is the authoritative source for payment status
 * transitions (paid → refunded, failed → paid after retry). These
 * tests lock down both the signature guard and the status mapping so a
 * forged request can't flip a payment row.
 */
class MoyasarWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('moyasar_webhook_secret', $this->secret);
        config()->set('services.moyasar.webhook_secret', $this->secret);
    }

    public function test_webhook_rejects_missing_signature_header(): void
    {
        $this->postJson('/webhooks/moyasar', ['type' => 'payment.paid'])
            ->assertStatus(401);
    }

    public function test_webhook_rejects_wrong_signature(): void
    {
        $body = json_encode(['type' => 'payment.paid', 'data' => ['id' => 'pay_x']]);

        $this->call(
            'POST', '/webhooks/moyasar',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Moyasar-Signature' => 'not-a-real-hmac'],
            $body,
        )->assertStatus(401);
    }

    public function test_webhook_accepts_valid_signature_for_unknown_payment(): void
    {
        // Valid signature but no matching Payment row — should 200 with
        // ignored message rather than crashing.
        $body = json_encode(['type' => 'payment.paid', 'data' => ['id' => 'pay_unknown']]);
        $sig  = hash_hmac('sha256', $body, $this->secret);

        $response = $this->call(
            'POST', '/webhooks/moyasar',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Moyasar-Signature' => $sig],
            $body,
        );

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_transitions_payment_to_refunded(): void
    {
        [$tenant, $payment] = $this->seedTenantAndPayment('pay_known_1', Payment::STATUS_PAID);

        $body = json_encode([
            'type' => 'payment.refunded',
            'data' => ['id' => 'pay_known_1', 'status' => 'refunded', 'amount' => 29900],
        ]);
        $sig = hash_hmac('sha256', $body, $this->secret);

        $this->call(
            'POST', '/webhooks/moyasar',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Moyasar-Signature' => $sig],
            $body,
        )->assertOk();

        $this->assertEquals(Payment::STATUS_REFUNDED, $payment->fresh()->status);
    }

    public function test_webhook_transitions_failed_to_paid_on_retry_success(): void
    {
        [$tenant, $payment] = $this->seedTenantAndPayment('pay_retry', Payment::STATUS_FAILED);

        $body = json_encode([
            'type' => 'payment.updated',
            'data' => ['id' => 'pay_retry', 'status' => 'paid', 'amount' => 29900],
        ]);
        $sig = hash_hmac('sha256', $body, $this->secret);

        $this->call(
            'POST', '/webhooks/moyasar',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Moyasar-Signature' => $sig],
            $body,
        )->assertOk();

        $fresh = $payment->fresh();
        $this->assertEquals(Payment::STATUS_PAID, $fresh->status);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_webhook_is_csrf_exempt(): void
    {
        // Regression guard: Moyasar can't send CSRF — the route MUST
        // be in bootstrap/app.php's validateCsrfTokens except list.
        // If this fails we'd get 419 instead of 401.
        $response = $this->post('/webhooks/moyasar', ['type' => 'x']);
        $response->assertStatus(401); // 401 (signature) NOT 419 (CSRF)
    }

    private function seedTenantAndPayment(string $moyasarId, string $status): array
    {
        $tenant = Tenant::create([
            'id'          => (string) Str::uuid(),
            'company_name' => 'Test Co',
            'owner_name'  => 'Owner',
            'owner_email' => 'o@test.local',
            'status'      => Tenant::STATUS_ACTIVE,
        ]);

        $payment = Payment::create([
            'tenant_id'          => $tenant->id,
            'moyasar_payment_id' => $moyasarId,
            'amount'             => 299,
            'currency'           => 'SAR',
            'status'             => $status,
            'payment_method'     => 'creditcard',
        ]);

        return [$tenant, $payment];
    }
}
