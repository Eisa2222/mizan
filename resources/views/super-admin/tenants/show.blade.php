@extends('super-admin.layouts.app')

@section('title', $tenant->company_name)
@section('heading', $tenant->company_name)

@section('content')
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:22px;margin-bottom:22px">
        <div class="sa-card">
            <h3 class="sa-card-title">بيانات المستأجر</h3>
            <table class="sa-table">
                <tr><td style="font-weight:600">الشركة</td><td>{{ $tenant->company_name }}</td></tr>
                <tr><td style="font-weight:600">المالك</td><td>{{ $tenant->owner_name }}</td></tr>
                <tr><td style="font-weight:600">البريد</td><td dir="ltr">{{ $tenant->owner_email }}</td></tr>
                <tr><td style="font-weight:600">الهاتف</td><td dir="ltr">{{ $tenant->owner_phone ?? '—' }}</td></tr>
                <tr><td style="font-weight:600">المنطقة الزمنية</td><td>{{ $tenant->timezone }}</td></tr>
                <tr><td style="font-weight:600">اللغة</td><td>{{ $tenant->language }}</td></tr>
                <tr><td style="font-weight:600">النطاقات</td><td>
                    @foreach ($tenant->domains as $d)
                        <span class="sa-badge sa-badge-grey" dir="ltr">{{ $d->domain }}</span>
                    @endforeach
                </td></tr>
                <tr><td style="font-weight:600">معرّف Tenant DB</td><td dir="ltr" style="font-family:monospace;font-size:12px">{{ $tenant->id }}</td></tr>
            </table>
        </div>

        <div class="sa-card">
            <h3 class="sa-card-title">إجراءات</h3>
            @if ($tenant->status === 'active')
                <form method="POST" action="{{ route('super-admin.tenants.suspend', $tenant) }}">@csrf
                    <button class="sa-btn sa-btn-ghost" style="width:100%;margin-bottom:8px">⏸ تعليق الحساب</button>
                </form>
            @else
                <form method="POST" action="{{ route('super-admin.tenants.activate', $tenant) }}">@csrf
                    <button class="sa-btn" style="width:100%;margin-bottom:8px">▶ تفعيل الحساب</button>
                </form>
            @endif

            <form method="POST" action="{{ route('super-admin.tenants.change-plan', $tenant) }}" style="margin-bottom:8px">@csrf
                <select name="plan_id" required class="form-input" style="width:100%;padding:6px 10px;border:1px solid #d4d9e3;border-radius:6px;margin-bottom:6px;font-family:inherit">
                    <option value="">— اختر باقة —</option>
                    @foreach ($plans as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
                <select name="billing_cycle" required class="form-input" style="width:100%;padding:6px 10px;border:1px solid #d4d9e3;border-radius:6px;margin-bottom:6px;font-family:inherit">
                    <option value="monthly">شهري</option>
                    <option value="yearly">سنوي</option>
                </select>
                <button class="sa-btn sa-btn-ghost" style="width:100%">تغيير الباقة</button>
            </form>

            <form method="POST" action="{{ route('super-admin.tenants.extend', $tenant) }}" style="margin-bottom:8px">@csrf
                <div style="display:flex;gap:6px">
                    <input name="days" type="number" min="1" max="365" value="30" required class="form-input" style="flex:1;padding:6px 10px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                    <button class="sa-btn sa-btn-ghost">تمديد</button>
                </div>
            </form>

            <form method="POST" action="{{ route('super-admin.tenants.destroy', $tenant) }}"
                  onsubmit="return confirm('حذف نهائي للمستأجر وقاعدة بياناته. متأكد؟')">
                @csrf @method('DELETE')
                <button class="sa-btn sa-btn-danger" style="width:100%">🗑 حذف نهائي</button>
            </form>
        </div>
    </div>

    <div class="sa-card" style="margin-bottom:22px">
        <h3 class="sa-card-title">الاشتراكات ({{ $tenant->subscriptions->count() }})</h3>
        @if ($tenant->subscriptions->isEmpty())
            <p style="color:#8a91a4;font-size:13px">لا يوجد اشتراكات.</p>
        @else
            <table class="sa-table">
                <thead><tr><th>الباقة</th><th>الدورة</th><th>الحالة</th><th>ينتهي</th><th>المبلغ</th></tr></thead>
                <tbody>
                    @foreach ($tenant->subscriptions as $s)
                        <tr>
                            <td>{{ $s->plan?->name }}</td>
                            <td>{{ $s->billing_cycle === 'monthly' ? 'شهري' : 'سنوي' }}</td>
                            <td><span class="sa-badge sa-badge-grey">{{ $s->status }}</span></td>
                            <td>{{ $s->ends_at?->format('Y-m-d') }}</td>
                            <td>{{ number_format($s->amount, 2) }} {{ $s->currency }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="sa-card">
        <h3 class="sa-card-title">المدفوعات ({{ $tenant->payments->count() }})</h3>
        @if ($tenant->payments->isEmpty())
            <p style="color:#8a91a4;font-size:13px">لا يوجد مدفوعات.</p>
        @else
            <table class="sa-table">
                <thead><tr><th>Moyasar ID</th><th>المبلغ</th><th>الطريقة</th><th>الحالة</th><th>التاريخ</th></tr></thead>
                <tbody>
                    @foreach ($tenant->payments as $p)
                        <tr>
                            <td dir="ltr" style="font-family:monospace;font-size:11px">{{ $p->moyasar_payment_id ?? '—' }}</td>
                            <td>{{ number_format($p->amount, 2) }} {{ $p->currency }}</td>
                            <td>{{ $p->payment_method ?? '—' }}</td>
                            <td><span class="sa-badge {{ $p->status === 'paid' ? 'sa-badge-green' : 'sa-badge-grey' }}">{{ $p->status }}</span></td>
                            <td>{{ $p->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
