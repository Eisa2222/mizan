@extends('super-admin.layouts.app')
@section('title', 'المدفوعات')
@section('heading', 'المدفوعات')

@section('content')
    <div class="sa-card" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:10px">
            <select name="status" class="form-input" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:8px;font-family:inherit">
                <option value="">كل الحالات</option>
                @foreach (['initiated'=>'تم البدء','paid'=>'مدفوع','failed'=>'فشل','refunded'=>'مُسترجع'] as $k=>$v)
                    <option value="{{ $k }}" {{ ($filters['status'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            <button class="sa-btn">تصفية</button>
        </form>
    </div>

    <div class="sa-card" style="padding:0;overflow:hidden">
        <table class="sa-table">
            <thead><tr><th>Moyasar ID</th><th>المستأجر</th><th>المبلغ</th><th>الطريقة</th><th>الحالة</th><th>تاريخ الدفع</th><th></th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td dir="ltr" style="font-family:monospace;font-size:11px">{{ $p->moyasar_payment_id ?? '—' }}</td>
                        <td><a href="{{ route('super-admin.tenants.show', $p->tenant_id) }}" style="color:#0b1220">{{ $p->tenant?->company_name ?? $p->tenant_id }}</a></td>
                        <td>{{ number_format($p->amount, 2) }} {{ $p->currency }}</td>
                        <td>{{ $p->payment_method ?? '—' }}</td>
                        <td><span class="sa-badge {{ $p->status === 'paid' ? 'sa-badge-green' : ($p->status === 'failed' ? 'sa-badge-red' : 'sa-badge-grey') }}">{{ $p->status }}</span></td>
                        <td>{{ $p->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>
                            @if ($p->isRefundable())
                                <form method="POST" action="{{ route('super-admin.payments.refund', $p) }}" onsubmit="return confirm('استرجاع الدفعة؟')">@csrf
                                    <button class="sa-btn sa-btn-ghost" style="padding:4px 10px">استرجاع</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;padding:32px;color:#8a91a4">لا توجد مدفوعات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $payments->links() }}</div>
@endsection
