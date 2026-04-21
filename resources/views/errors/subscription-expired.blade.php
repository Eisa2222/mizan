@extends('errors.layout')

@section('code', '402 · EXPIRED')
@section('icon_style', 'background:#fff2d6;color:#8a5a0f')
@section('icon', '⏰')
@section('title', 'انتهى اشتراكك')

@section('message')
    <p>انتهت صلاحية اشتراك <strong>{{ $tenant->company_name ?? '' }}</strong>.</p>

    @if ($subscription)
        <div style="background:#f5f7fb;border-radius:8px;padding:12px 16px;margin:18px 0;font-size:13px">
            الباقة: <strong>{{ $subscription->plan?->name ?? '—' }}</strong><br>
            انتهت في: <strong>{{ $subscription->ends_at?->translatedFormat('d F Y') }}</strong>
        </div>
    @endif

    <p>للاستمرار، جدّد اشتراكك من صفحة الباقات:</p>
@endsection

@section('action')
    <a href="{{ url('/') }}" class="btn">عرض الباقات ←</a>
@endsection
