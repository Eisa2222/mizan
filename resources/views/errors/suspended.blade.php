@extends('errors.layout')

@section('code', '403 · SUSPENDED')
@section('icon_style', 'background:#fbe7e7;color:#c75c5c')
@section('icon', '⏸')
@section('title', 'الحساب موقوف مؤقتاً')

@section('message')
    <p>تم تعليق حساب <strong>{{ $tenant->company_name ?? '' }}</strong> من قبل مدير النظام.</p>
    <p>للاستفسار أو إعادة التفعيل، يُرجى التواصل مع الدعم الفني:</p>
    @if ($support = \App\Models\SystemSetting::get('support_email'))
        <p><code dir="ltr">{{ $support }}</code></p>
    @endif
@endsection
