@extends('super-admin.layouts.app')
@section('title', 'الباقات')
@section('heading', 'الباقات')

@section('content')
    <div style="margin-bottom:16px;text-align:end">
        <a href="{{ route('super-admin.plans.create') }}" class="sa-btn">+ باقة جديدة</a>
    </div>

    <div class="sa-card" style="padding:0;overflow:hidden">
        <table class="sa-table">
            <thead><tr><th>الاسم</th><th>Slug</th><th>شهري</th><th>سنوي</th><th>تجربة</th><th>مشتركون</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td><strong>{{ $plan->name }}</strong>@if ($plan->is_featured) <span class="sa-badge sa-badge-amber" style="font-size:10px">مميّز</span>@endif</td>
                        <td dir="ltr" style="font-size:12px;color:#8a91a4">{{ $plan->slug }}</td>
                        <td>{{ number_format($plan->price_monthly, 2) }} {{ $plan->currency }}</td>
                        <td>
                            {{ number_format($plan->price_yearly, 2) }} {{ $plan->currency }}
                            @if ($plan->yearly_savings_percent > 0)
                                <span class="sa-badge sa-badge-green" style="font-size:10px">وفّر {{ $plan->yearly_savings_percent }}%</span>
                            @endif
                        </td>
                        <td>{{ $plan->trial_days }} يوم</td>
                        <td>{{ $plan->subscriptions_count ?? 0 }}</td>
                        <td>
                            @if ($plan->is_active)  <span class="sa-badge sa-badge-green">نشط</span>
                            @else  <span class="sa-badge sa-badge-grey">موقوف</span>  @endif
                        </td>
                        <td style="display:flex;gap:4px">
                            <a href="{{ route('super-admin.plans.edit', $plan) }}" class="sa-btn sa-btn-ghost" style="padding:4px 10px">تعديل</a>
                            <form method="POST" action="{{ route('super-admin.plans.toggle', $plan) }}">@csrf
                                <button class="sa-btn sa-btn-ghost" style="padding:4px 10px">{{ $plan->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:32px;color:#8a91a4">لا توجد باقات. <a href="{{ route('super-admin.plans.create') }}" style="color:#c8a94b">أضف أول باقة.</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
