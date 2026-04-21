@extends('super-admin.layouts.app')

@section('title', 'الرئيسية')
@section('heading', 'نظرة عامة')

@section('content')
    <div class="sa-stat-grid">
        <div class="sa-stat">
            <div class="sa-stat-label">إجمالي المستأجرين</div>
            <div class="sa-stat-value">{{ number_format($tenants['total']) }}</div>
            <div class="sa-stat-delta">{{ $tenants['active'] }} نشط · {{ $tenants['suspended'] }} موقوف</div>
        </div>
        <div class="sa-stat">
            <div class="sa-stat-label">الإيرادات الإجمالية</div>
            <div class="sa-stat-value">{{ number_format($revenue['total'], 2) }}</div>
            <div class="sa-stat-delta">ريال سعودي</div>
        </div>
        <div class="sa-stat">
            <div class="sa-stat-label">إيرادات هذا الشهر</div>
            <div class="sa-stat-value">{{ number_format($revenue['this_month'], 2) }}</div>
            @php
                $change = $revenue['last_month'] > 0
                    ? round((($revenue['this_month'] - $revenue['last_month']) / $revenue['last_month']) * 100, 1)
                    : null;
            @endphp
            @if ($change !== null)
                <div class="sa-stat-delta {{ $change < 0 ? 'neg' : '' }}">
                    {{ $change >= 0 ? '↑' : '↓' }} {{ abs($change) }}% عن الشهر الماضي
                </div>
            @else
                <div class="sa-stat-delta">—</div>
            @endif
        </div>
        <div class="sa-stat">
            <div class="sa-stat-label">الاشتراكات النشطة</div>
            <div class="sa-stat-value">{{ number_format($subscriptions['active']) }}</div>
            <div class="sa-stat-delta {{ $subscriptions['expiring_soon'] > 0 ? 'neg' : '' }}">
                {{ $subscriptions['expiring_soon'] }} تنتهي خلال ٧ أيام
            </div>
        </div>
    </div>

    <div class="sa-card" style="margin-bottom:24px">
        <h3 class="sa-card-title">الإيرادات — آخر ١٢ شهراً</h3>
        @if (collect($revenue_chart)->sum('amount') === 0.0)
            <div style="padding:32px;text-align:center;color:#8a91a4;font-size:13px">لا توجد مدفوعات مسجّلة بعد.</div>
        @else
            <canvas id="revenueChart" height="80"></canvas>
            @push('scripts')
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
                <script>
                    const ctx = document.getElementById('revenueChart');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: @json(collect($revenue_chart)->pluck('month')),
                            datasets: [{
                                label: 'ريال',
                                data: @json(collect($revenue_chart)->pluck('amount')),
                                borderColor: '#c8a94b',
                                backgroundColor: 'rgba(200,169,75,0.15)',
                                fill: true, tension: 0.3, borderWidth: 2, pointRadius: 3,
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { callback: v => Number(v).toLocaleString() } } }
                        }
                    });
                </script>
            @endpush
        @endif
    </div>

    <div class="sa-card">
        <h3 class="sa-card-title">أحدث المستأجرين</h3>
        @if ($recent_tenants->isEmpty())
            <div style="padding:24px;text-align:center;color:#8a91a4;font-size:13px">لا يوجد مستأجرون بعد — شارك رابط صفحة الهبوط لبدء الاستقبال.</div>
        @else
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>الشركة</th>
                        <th>المالك</th>
                        <th>الباقة</th>
                        <th>الحالة</th>
                        <th>التسجيل</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent_tenants as $tenant)
                        <tr>
                            <td>{{ $tenant->company_name }}</td>
                            <td>{{ $tenant->owner_name }} · <span style="color:#8a91a4" dir="ltr">{{ $tenant->owner_email }}</span></td>
                            <td>{{ $tenant->activeSubscription?->plan?->name ?? '—' }}</td>
                            <td>
                                @switch($tenant->status)
                                    @case('active')    <span class="sa-badge sa-badge-green">نشط</span> @break
                                    @case('suspended') <span class="sa-badge sa-badge-red">موقوف</span> @break
                                    @case('archived')  <span class="sa-badge sa-badge-grey">مؤرشف</span> @break
                                    @default          <span class="sa-badge sa-badge-grey">{{ $tenant->status }}</span>
                                @endswitch
                            </td>
                            <td>{{ $tenant->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
