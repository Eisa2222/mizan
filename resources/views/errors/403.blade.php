@extends('errors.layout')

@section('code', '403')
@section('icon_style', 'background:#fbe7e7;color:#933')
@section('icon', '🔒')
@section('title', 'ليس لديك صلاحية الوصول')

@section('message')
    <p>هذه الصفحة محمية ولا يملك حسابك صلاحية فتحها. إذا كنت تعتقد أن هذا خطأ، تواصل مع مدير حسابك.</p>
    @if (! empty($exception) && $exception->getMessage())
        <p><code>{{ $exception->getMessage() }}</code></p>
    @endif
@endsection

@section('action')
    <a href="{{ url('/') }}" class="btn">العودة للرئيسية</a>
@endsection
