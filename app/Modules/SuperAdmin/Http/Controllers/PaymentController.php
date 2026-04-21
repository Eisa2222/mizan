<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\MoyasarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Read-only payment history + refund action. Refund hits Moyasar's API
 * via MoyasarService; we only mark `status=refunded` locally once the
 * API call succeeds. The webhook will confirm the transition again
 * server-to-server — we treat the API response as optimistic and the
 * webhook as authoritative.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly MoyasarService $moyasar) {}

    public function index(Request $request): View
    {
        $query = Payment::query()->with(['tenant', 'subscription.plan']);

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        return view('super-admin.payments.index', [
            'payments' => $query->latest()->paginate(25)->withQueryString(),
            'filters'  => $request->only(['status']),
        ]);
    }

    public function refund(Payment $payment): RedirectResponse
    {
        if (! $payment->isRefundable()) {
            return back()->with('error', 'هذه العملية غير قابلة للاسترجاع.');
        }

        try {
            $response = $this->moyasar->refundPayment($payment->moyasar_payment_id);
        } catch (\Throwable $e) {
            Log::error('Moyasar refund failed', [
                'payment_id' => $payment->id,
                'moyasar_id' => $payment->moyasar_payment_id,
                'error'      => $e->getMessage(),
            ]);
            return back()->with('error', 'فشل الاتصال بـ Moyasar: ' . $e->getMessage());
        }

        if (($response['status'] ?? null) !== 'refunded') {
            return back()->with('error', 'لم يتم قبول طلب الاسترجاع: ' . ($response['message'] ?? 'رد غير معروف'));
        }

        $payment->update([
            'status'           => Payment::STATUS_REFUNDED,
            'moyasar_response' => $response,
        ]);

        return back()->with('success', 'تم استرجاع الدفعة.');
    }
}
