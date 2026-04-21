@extends('errors.layout')

@section('code', '429')
@section('icon_style', 'background:#fff2d6;color:#8a5a0f')
@section('icon', '⏳')
@section('title', 'طلبات كثيرة في وقت قصير')

@section('message')
    <p>لحمايتك — طلبات كثيرة من جهازك خلال فترة قصيرة. انتظر قليلاً ثم أعد المحاولة.</p>
@endsection

@section('action')
    <a href="javascript:location.reload()" class="btn">إعادة المحاولة</a>
@endsection
