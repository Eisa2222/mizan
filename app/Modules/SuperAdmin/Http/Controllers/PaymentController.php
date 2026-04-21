<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only payment history + refund action. Actual Moyasar refund API
 * call lives in MoyasarService (Phase 3) — this controller just marks
 * the row and is a no-op until that service is wired.
 */
class PaymentController extends Controller
{
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

        // TODO (Phase 3): call MoyasarService::refund($payment->moyasar_payment_id)
        // and update the row inside the service from the webhook response.
        $payment->update(['status' => Payment::STATUS_REFUNDED]);

        return back()->with('success', 'تم استرجاع الدفعة.');
    }
}
