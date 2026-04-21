@extends('errors.layout')

@section('code', '500')
@section('icon_style', 'background:#fbe7e7;color:#933')
@section('icon', '⚠')
@section('title', 'حدث خطأ غير متوقّع')

@section('message')
    <p>نعتذر — هناك عطل مؤقت في الخادم. فريقنا التقني تم إبلاغه تلقائياً وسيُعالج الأمر قريباً.</p>
    <p>إذا استمرّ الخطأ، تواصل مع الدعم الفني.</p>
@endsection

@section('action')
    <div>
        <a href="{{ url('/') }}" class="btn">العودة للرئيسية</a>
        <a href="javascript:location.reload()" class="btn btn-ghost">إعادة المحاولة</a>
    </div>
@endsection
