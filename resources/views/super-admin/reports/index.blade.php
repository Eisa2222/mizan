@extends('super-admin.layouts.app')

@section('title', 'التقارير')
@section('heading', 'التقارير')

@section('content')
    {{-- Period filter --}}
    <form method="GET" action="{{ route('super-admin.reports.index') }}" class="sa-card" style="margin-bottom:16px">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div style="min-width:180px">
                <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">الفترة</label>
                <select name="period" style="padding:7px 10px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                    @foreach ([
                        'last_7'         => 'آخر ٧ أيام',
                        'last_30'        => 'آخر ٣٠ يوم',
                        'last_90'        => 'آخر ٩٠ يوم',
                        'last_12_months' => 'آخر ١٢ شهر',
                        'ytd'            => 'من بداية السنة',
                    ] as $k => $v)
                        <option value="{{ $k }}" {{ $period === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:140px">
                <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">التجميع</label>
                <select name="group_by" style="padding:7px 10px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                    <option value="day"   {{ $group_by === 'day'   ? 'selected' : '' }}>يومي</option>
                    <option value="week"  {{ $group_by === 'week'  ? 'selected' : '' }}>أسبوعي</option>
                    <option value="month" {{ $group_by === 'month' ? 'selected' : '' }}>شهري</option>
                </select>
            </div>
            <button class="sa-btn">تحديث</button>
            <a href="{{ route('super-admin.reports.export', ['period' => $period]) }}" class="sa-btn sa-btn-ghost">⬇ CSV</a>
        </div>

        <div style="font-size:12px;color:#8a91a4;margin-top:12px;direction:ltr">
            {{ $start->format('Y-m-d') }} → {{ $end->format('Y-m-d') }}
        </div>
    </form>

    {{-- Headline KPIs --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px">
        <div class="sa-stat" style="background:#dff6e8">
            <div class="sa-stat-label">إجمالي الإيرادات</div>
            <div class="sa-stat-value" style="color:#1e6c44">{{ number_format($totals['revenue'], 2) }} <small style="font-size:13px">ر.س</small></div>
        </div>
        <div class="sa-stat">
            <div class="sa-stat-label">عدد المدفوعات</div>
            <div class="sa-stat-value">{{ number_format($totals['count']) }}</div>
        </div>
        <div class="sa-stat" style="background:#fff2d6">
            <div class="sa-stat-label">مستأجرون جدد</div>
            <div class="sa-stat-value" style="color:#8a5a0f">{{ $totals['tenants'] }}</div>
        </div>
        <div class="sa-stat">
            <div class="sa-stat-label">اشتراكات جديدة</div>
            <div class="sa-stat-value">{{ $totals['new_subs'] }}</div>
        </div>
        <div class="sa-stat" style="background:#fbe7e7">
            <div class="sa-stat-label">Churn</div>
            <div class="sa-stat-value" style="color:#933">{{ $totals['churn'] }}</div>
        </div>
    </div>

    {{-- Revenue chart / table --}}
    <div class="sa-card" style="padding:20px">
        <h3 class="sa-card-title">الإيرادات {{ ['day'=>'اليومية','week'=>'الأسبوعية','month'=>'الشهرية'][$group_by] ?? '' }}</h3>

        @if ($buckets->isEmpty())
            <p style="color:#8a91a4;text-align:center;padding:30px">لا توجد مدفوعات ضمن هذه الفترة.</p>
        @else
            {{-- Simple CSS bar chart (no external JS) --}}
            @php $maxRevenue = $buckets->max('revenue') ?: 1; @endphp
            <div style="display:flex;flex-direction:column;gap:6px;margin:18px 0 4px">
                @foreach ($buckets as $label => $bucket)
                    @php $width = ($bucket['revenue'] / $maxRevenue) * 100; @endphp
                    <div style="display:flex;align-items:center;gap:10px;font-size:12px">
                        <div style="width:90px;color:#485068;direction:ltr;text-align:end;font-family:monospace;font-size:11px">{{ $label }}</div>
                        <div style="flex:1;height:22px;background:#f5f7fb;border-radius:4px;overflow:hidden;position:relative">
                            <div style="width:{{ $width }}%;height:100%;background:linear-gradient(90deg,#1e6c44,#2f9e6a)"></div>
                        </div>
                        <div style="width:100px;text-align:start;color:#0b1220;font-weight:600;font-family:monospace">
                            {{ number_format($bucket['revenue'], 0) }} ر.س
                        </div>
                        <div style="width:60px;color:#8a91a4;font-size:11px;text-align:end">{{ $bucket['count'] }} دفعة</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
