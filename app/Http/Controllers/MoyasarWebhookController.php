<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MoyasarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook receiver for Moyasar payment lifecycle events. Moyasar calls
 * this endpoint when a payment transitions server-side (e.g. async
 * 3-D Secure finalises, a refund posts, a failed retry succeeds).
 *
 * Signature verification: the request body is HMAC-SHA256'd with the
 * webhook secret stored in `system_settings.moyasar_webhook_secret`.
 * We reject anything that doesn't match — no signature, no update.
 *
 * CSRF must be excluded from this route in bootstrap/app.php (Moyasar
 * cannot send our CSRF token).
 */
class MoyasarWebhookController extends Controller
{
    public function __construct(private readonly MoyasarService $moyasar) {}

    public function __invoke(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $signature = (string) $request->header('X-Moyasar-Signature', '');

        if (! $this->moyasar->verifyWebhook($body, $signature)) {
            Log::warning('Moyasar webhook: invalid signature', [
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $payload = json_decode($body, true) ?: [];
        $event = (string) ($payload['type'] ?? '');
        $data  = $payload['data'] ?? [];

        $paymentId = (string) ($data['id'] ?? '');
        if ($paymentId === '') {
            return response()->json(['ok' => true, 'ignored' => 'no payment id']);
        }

        $payment = Payment::where('moyasar_payment_id', $paymentId)->first();
        if (! $payment) {
            Log::info('Moyasar webhook: unknown payment', ['event' => $event, 'id' => $paymentId]);
            return response()->json(['ok' => true, 'ignored' => 'unknown payment']);
        }

        // Map Moyasar status to our canonical status enum.
        $newStatus = match ($data['status'] ?? null) {
            'paid'     => Payment::STATUS_PAID,
            'failed'   => Payment::STATUS_FAILED,
            'refunded' => Payment::STATUS_REFUNDED,
            default    => null,
        };

        if ($newStatus !== null && $newStatus !== $payment->status) {
            $payment->update([
                'status'           => $newStatus,
                'moyasar_response' => $data,
                'paid_at'          => $newStatus === Payment::STATUS_PAID ? now() : $payment->paid_at,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
