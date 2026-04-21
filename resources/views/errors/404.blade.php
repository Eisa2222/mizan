@extends('errors.layout')

@section('code', '404')
@section('icon_style', 'background:#eef1f6;color:#485068')
@section('icon', '🔍')
@section('title', 'لم نعثر على هذه الصفحة')

@section('message')
    <p>الرابط الذي تبحث عنه إمّا نُقل أو لم يعد متاحاً. تأكّد من العنوان أو عُد للصفحة الرئيسية.</p>
@endsection

@section('action')
    <div>
        <a href="{{ url('/') }}" class="btn">العودة للرئيسية</a>
        <a href="javascript:history.back()" class="btn btn-ghost">الصفحة السابقة</a>
    </div>
@endsection
