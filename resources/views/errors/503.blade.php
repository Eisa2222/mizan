@extends('errors.layout')

@section('code', '503')
@section('icon_style', 'background:#fff2d6;color:#8a5a0f')
@section('icon', '🛠')
@section('title', 'الخدمة في صيانة')

@section('message')
    <p>نُجري الآن تحديثاً مُجدولاً — ستعود الخدمة خلال دقائق. شكراً لصبرك.</p>
@endsection

@section('action')
    <a href="javascript:location.reload()" class="btn">إعادة المحاولة</a>
@endsection
