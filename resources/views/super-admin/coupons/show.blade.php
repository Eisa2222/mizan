@extends('super-admin.layouts.app')
@section('title', $coupon->code)
@section('heading', 'الكوبون: '.$coupon->code)

@section('content')
    <div class="sa-stat-grid" style="grid-template-columns:repeat(3,1fr)">
        <div class="sa-stat">
            <div class="sa-stat-label">إجمالي الاستخدامات</div>
            <div class="sa-stat-value">{{ $coupon->uses_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</div>
        </div>
        <div class="sa-stat">
            <div class="sa-stat-label">إجمالي الخصم الممنوح</div>
            <div class="sa-stat-value">{{ number_format((float) $totalDiscount, 2) }} SAR</div>
        </div>
        <div class="sa-stat">
            <div class="sa-stat-label">متوسط الخصم</div>
            <div class="sa-stat-value">{{ number_format((float) $avgDiscount, 2) }} SAR</div>
        </div>
    </div>

    <div class="sa-card">
        <h3 class="sa-card-title">سجل الاستخدامات</h3>
        @if ($coupon->uses->isEmpty())
            <p style="color:#8a91a4;font-size:13px">لم يُستخدم بعد.</p>
        @else
            <table class="sa-table">
                <thead><tr><th>الشركة</th><th>الباقة</th><th>مبلغ الخصم</th><th>تاريخ الاستخدام</th></tr></thead>
                <tbody>
                    @foreach ($coupon->uses as $u)
                        <tr>
                            <td>{{ $u->tenant?->company_name ?? $u->tenant_id }}</td>
                            <td>{{ $u->subscription?->plan?->name ?? '—' }}</td>
                            <td>{{ number_format($u->discount_amount, 2) }} SAR</td>
                            <td>{{ $u->used_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
