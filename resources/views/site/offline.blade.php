@extends('errors.layout')
@section('code', \App\Support\Branding::name())
@section('title', __('لا يوجد اتصال بالإنترنت'))
@section('message', __('تحقّق من اتصالك ثم أعد المحاولة. بياناتك محفوظة لدى المكتب ولن يضيع شيء.'))
@section('actions')
    <a class="btn" href="javascript:location.reload()">{{ __('إعادة المحاولة') }}</a>
@endsection
