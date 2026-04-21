<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Subscription::query()->with(['tenant', 'plan']);

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($cycle = $request->string('billing_cycle')->trim()->toString()) {
            $query->where('billing_cycle', $cycle);
        }

        return view('super-admin.subscriptions.index', [
            'subscriptions' => $query->latest()->paginate(25)->withQueryString(),
            'filters'       => $request->only(['status', 'billing_cycle']),
        ]);
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        $subscription->update([
            'status'      => Subscription::STATUS_CANCELED,
            'canceled_at' => now(),
        ]);

        return back()->with('success', 'تم إلغاء الاشتراك.');
    }
}
