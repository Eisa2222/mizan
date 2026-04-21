<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around Moyasar's REST API (api.moyasar.com/v1). Keys are
 * pulled from Laravel's Config (populated at boot by ApplySystemSettings
 * from the encrypted `system_settings` row) so secrets never leak to env
 * files in the installed product.
 *
 * Money math note: Moyasar expects amounts in *halalas* (hundredths of
 * SAR), not whole riyals — every amount passed in is multiplied by 100
 * inside this service. Callers hand over human-friendly decimals; the
 * wire format is handled here.
 */
class MoyasarService
{
    private const BASE = 'https://api.moyasar.com/v1';

    /**
     * Create a payment. Moyasar responds with an `id` + `source.transaction_url`
     * the tenant's browser should be redirected to for 3-D Secure / STC Pay
     * confirmation. For test cards + direct charges, `status=paid` comes
     * back immediately.
     *
     * @param array{
     *   amount: float,              // SAR, not halalas
     *   currency?: string,
     *   description: string,
     *   callback_url: string,
     *   source: array<string, mixed>, // {type: 'creditcard'|'applepay'|'stcpay', ...}
     *   metadata?: array<string, mixed>,
     * } $payload
     *
     * @return array<string, mixed> Moyasar JSON response (decoded).
     */
    public function createPayment(array $payload): array
    {
        $this->ensureSecret();

        $body = array_filter([
            'amount'       => (int) round($payload['amount'] * 100),
            'currency'     => $payload['currency']    ?? 'SAR',
            'description'  => $payload['description'],
            'callback_url' => $payload['callback_url'],
            'source'       => $payload['source'],
            'metadata'     => $payload['metadata']    ?? null,
        ], fn ($v) => $v !== null);

        $response = Http::withBasicAuth($this->secret(), '')
            ->timeout(20)
            ->acceptJson()
            ->post(self::BASE.'/payments', $body);

        return $this->unwrap($response, 'createPayment');
    }

    /**
     * Fetch an existing payment's current status. Used by the callback
     * handler to verify client-reported success before issuing the
     * tenant DB + welcome mail.
     */
    public function getPayment(string $paymentId): array
    {
        $this->ensureSecret();

        $response = Http::withBasicAuth($this->secret(), '')
            ->timeout(15)
            ->acceptJson()
            ->get(self::BASE."/payments/{$paymentId}");

        return $this->unwrap($response, "getPayment[{$paymentId}]");
    }

    /**
     * Refund a paid charge. Amount in SAR — Moyasar accepts partial
     * refunds if `amount` < original. Null amount = full refund.
     */
    public function refundPayment(string $paymentId, ?float $amount = null): array
    {
        $this->ensureSecret();

        $body = $amount !== null
            ? ['amount' => (int) round($amount * 100)]
            : [];

        $response = Http::withBasicAuth($this->secret(), '')
            ->timeout(20)
            ->acceptJson()
            ->post(self::BASE."/payments/{$paymentId}/refund", $body);

        return $this->unwrap($response, "refundPayment[{$paymentId}]");
    }

    /**
     * Verify an incoming webhook signature. Moyasar signs the body with
     * the webhook secret using HMAC-SHA256 and puts it in the
     * `X-Moyasar-Signature` header. We recompute it and constant-time
     * compare to the provided value.
     */
    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) Config::get('services.moyasar.webhook_secret');
        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    private function ensureSecret(): void
    {
        if (empty($this->secret())) {
            throw new RuntimeException('Moyasar secret key is not configured. Open /super-admin/settings/moyasar to set it.');
        }
    }

    private function secret(): string
    {
        return (string) Config::get('services.moyasar.secret_key');
    }

    private function unwrap(Response $response, string $context): array
    {
        $json = $response->json();

        if (! $response->successful()) {
            Log::warning("Moyasar API call failed [{$context}]", [
                'status' => $response->status(),
                'body'   => $json,
            ]);
        }

        return is_array($json) ? $json : ['raw' => $response->body()];
    }
}
