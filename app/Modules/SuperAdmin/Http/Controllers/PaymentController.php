<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AuditLogger;
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
        $query = $this->filterQuery($request);

        return view('super-admin.payments.index', [
            'payments' => $query->latest()->paginate(25)->withQueryString(),
            'filters'  => $request->only(['status']),
        ]);
    }

    /**
     * Stream payments matching the current filter window as CSV. No
     * pagination — uses chunked reads (500 rows) so large exports don't
     * OOM. BOM prepended so Excel reads UTF-8 Arabic correctly.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = $this->filterQuery($request);
        $filename = 'payments-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'payment_id', 'tenant', 'plan', 'amount', 'currency',
                'status', 'method', 'moyasar_id', 'paid_at', 'created_at',
            ]);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->id,
                        $p->tenant?->company_name,
                        $p->subscription?->plan?->name,
                        $p->amount,
                        $p->currency,
                        $p->status,
                        $p->payment_method,
                        $p->moyasar_payment_id,
                        $p->paid_at?->toIso8601String(),
                        $p->created_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function filterQuery(Request $request)
    {
        $q = Payment::query()->with(['tenant', 'subscription.plan']);

        if ($status = $request->string('status')->trim()->toString()) {
            $q->where('status', $status);
        }

        return $q;
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

        $beforeStatus = $payment->status;
        $payment->update([
            'status'           => Payment::STATUS_REFUNDED,
            'moyasar_response' => $response,
        ]);

        AuditLogger::record('payment.refund', $payment,
            before: ['status' => $beforeStatus],
            after:  ['status' => Payment::STATUS_REFUNDED, 'moyasar_payment_id' => $payment->moyasar_payment_id],
        );

        return back()->with('success', 'تم استرجاع الدفعة.');
    }
}
