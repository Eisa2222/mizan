@extends('super-admin.layouts.app')
@section('title', 'الاشتراكات')
@section('heading', 'الاشتراكات')

@section('content')
    <div class="sa-card" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:10px">
            <select name="status" class="form-input" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:8px;font-family:inherit">
                <option value="">كل الحالات</option>
                @foreach (['trialing'=>'تجربة','active'=>'نشط','past_due'=>'متأخر','canceled'=>'ملغى','expired'=>'منتهي','suspended'=>'موقوف'] as $k=>$v)
                    <option value="{{ $k }}" {{ ($filters['status'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            <select name="billing_cycle" class="form-input" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:8px;font-family:inherit">
                <option value="">كل الدورات</option>
                <option value="monthly" {{ ($filters['billing_cycle'] ?? '') === 'monthly' ? 'selected' : '' }}>شهري</option>
                <option value="yearly"  {{ ($filters['billing_cycle'] ?? '') === 'yearly'  ? 'selected' : '' }}>سنوي</option>
            </select>
            <button class="sa-btn">تصفية</button>
        </form>
    </div>

    <div class="sa-card" style="padding:0;overflow:hidden">
        <table class="sa-table">
            <thead><tr><th>المستأجر</th><th>الباقة</th><th>الدورة</th><th>الحالة</th><th>يبدأ</th><th>ينتهي</th><th>المبلغ</th><th></th></tr></thead>
            <tbody>
                @forelse ($subscriptions as $s)
                    <tr>
                        <td><a href="{{ route('super-admin.tenants.show', $s->tenant_id) }}" style="color:#0b1220">{{ $s->tenant?->company_name ?? '—' }}</a></td>
                        <td>{{ $s->plan?->name }}</td>
                        <td>{{ $s->billing_cycle === 'monthly' ? 'شهري' : 'سنوي' }}</td>
                        <td><span class="sa-badge sa-badge-grey">{{ $s->status }}</span></td>
                        <td>{{ $s->starts_at?->format('Y-m-d') }}</td>
                        <td>{{ $s->ends_at?->format('Y-m-d') }}</td>
                        <td>{{ number_format($s->amount, 2) }} {{ $s->currency }}</td>
                        <td>
                            @if (in_array($s->status, ['trialing','active']))
                                <form method="POST" action="{{ route('super-admin.subscriptions.cancel', $s) }}" onsubmit="return confirm('إلغاء الاشتراك؟')">@csrf
                                    <button class="sa-btn sa-btn-ghost" style="padding:4px 10px">إلغاء</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:32px;color:#8a91a4">لا توجد اشتراكات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $subscriptions->links() }}</div>
@endsection
