<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>الاشتراك والفوترة · {{ $tenant->company_name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Cairo','Tajawal',sans-serif; background: #f7f8fc; margin: 0; padding: 32px 16px; color: #1a2138; }
        .wrap { max-width: 960px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #e3e7f0; border-radius: 14px; padding: 26px; margin-bottom: 18px; }
        h1 { font-size: 24px; margin: 0 0 6px; }
        .sub { color: #6a7289; margin: 0 0 22px; font-size: 14px; }
        h2 { font-size: 15px; margin: 0 0 14px; color: #0b1220; }
        .status-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; }
        @media (max-width: 760px) { .status-row { grid-template-columns: 1fr; } }
        .stat-label { font-size: 12px; color: #6a7289; }
        .stat-value { font-size: 20px; font-weight: 700; color: #0b1220; margin-top: 4px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #dff6e8; color: #1e6c44; }
        .badge-amber { background: #fff2d6; color: #8a5a0f; }
        .badge-red   { background: #fbe7e7; color: #933; }

        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .plan { background: #fff; border: 1px solid #e3e7f0; border-radius: 12px; padding: 22px 20px; }
        .plan.current { border-color: #c8a94b; border-width: 2px; }
        .plan-name { font-size: 17px; font-weight: 700; margin: 0 0 6px; }
        .plan-price { font-size: 26px; font-weight: 800; color: #0b1220; margin: 0 0 8px; }
        .plan-price small { font-size: 13px; color: #8a91a4; font-weight: 500; }
        .plan-feats { list-style: none; padding: 0; margin: 0 0 16px; font-size: 13px; color: #2b344a; }
        .plan-feats li { padding: 3px 0; }
        .btn { display: inline-block; padding: 10px 22px; background: #c8a94b; color: #0b1220; text-decoration: none; border-radius: 8px; font-weight: 700; font-family: inherit; border: none; cursor: pointer; font-size: 13px; }
        .btn-ghost { background: transparent; border: 1px solid #d4d9e3; color: #485068; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>الاشتراك والفوترة</h1>
        <p class="sub">{{ $tenant->company_name }} — معلومات حسابك وخيارات الترقية.</p>

        {{-- Current subscription card --}}
        <div class="card">
            <h2>الاشتراك الحالي</h2>
            @if ($current)
                <div class="status-row">
                    <div>
                        <div class="stat-label">الباقة</div>
                        <div class="stat-value">{{ $current->plan?->name ?? '—' }}</div>
                        <div style="font-size:12px;color:#8a91a4;margin-top:2px">
                            {{ $current->billing_cycle === 'yearly' ? 'سنوي' : 'شهري' }} · {{ number_format($current->amount, 2) }} {{ $current->currency }}
                        </div>
                    </div>
                    <div>
                        <div class="stat-label">الحالة</div>
                        <div class="stat-value" style="font-size:14px">
                            @if ($current->isTrialing())
                                <span class="badge badge-amber">تجربة</span>
                            @elseif ($current->isActive())
                                <span class="badge badge-green">نشط</span>
                            @elseif ($current->isExpired())
                                <span class="badge badge-red">منتهي</span>
                            @else
                                <span class="badge">{{ $current->status }}</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="stat-label">ينتهي في</div>
                        <div class="stat-value" style="font-size:16px">{{ $current->ends_at?->translatedFormat('d F Y') }}</div>
                        @if (($days = $current->daysUntilExpiry()) !== null && $days >= 0)
                            <div style="font-size:12px;color:#8a91a4;margin-top:2px">بعد {{ $days }} يوم</div>
                        @endif
                    </div>
                </div>
            @else
                <p style="color:#8a91a4;font-size:14px">لا يوجد اشتراك نشط. اختر باقة أدناه للبدء.</p>
            @endif
        </div>

        {{-- Plans list --}}
        <h2 style="margin:28px 0 14px;font-size:18px">خيارات الباقات</h2>
        <div class="plans-grid">
            @foreach ($plans as $plan)
                @php $isCurrent = $current && $current->plan_id === $plan->id; @endphp
                <div class="plan {{ $isCurrent ? 'current' : '' }}">
                    <div class="plan-name">
                        {{ $plan->name }}
                        @if ($isCurrent) <span class="badge badge-amber" style="font-size:10px">الباقة الحالية</span> @endif
                    </div>
                    <div class="plan-price">
                        {{ number_format($plan->price_monthly, 0) }}
                        <small>{{ $plan->currency }} / شهر</small>
                    </div>
                    <ul class="plan-feats">
                        @foreach ($plan->planFeatures as $pf)
                            <li>✓ {{ $pf->feature }}</li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('tenant.billing.renew', $plan) }}">
                        @csrf
                        <button type="submit" class="btn {{ $isCurrent ? 'btn-ghost' : '' }}">
                            {{ $isCurrent ? 'تجديد نفس الباقة' : 'اشترك في هذه الباقة' }} ←
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        <p style="font-size:12px;color:#8a91a4;margin-top:22px;text-align:center">
            الضغط على "اشترك" سينقلك لصفحة الدفع الآمنة على الدومين الرئيسي.
        </p>
    </div>
</body>
</html>
