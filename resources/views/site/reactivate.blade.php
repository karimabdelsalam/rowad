@extends('errors.layout')
@php($ar = app()->getLocale() !== 'en')
@section('code', $tenant->name)
@section('title', $canReactivate ? __('إعادة تفعيل المؤسسة') : __('انتهت فترة الاحتفاظ بالبيانات'))
@section('message')
    @if ($canReactivate)
        {{ __('تعود المؤسسة كما كانت تمامًا: الوحدات والعقود والسندات والمستخدمون. تبقى بياناتك محفوظة حتى :date إن لم تُعد التفعيل.', ['date' => $tenant->purge_after->format('Y-m-d')]) }}
    @else
        {{ __('لم تعد هذه المؤسسة قابلة لإعادة التفعيل من هذا الرابط. تواصل مع إدارة المنصة.') }}
    @endif
@endsection
@section('actions')
    @error('reactivate')<p style="color:#b91c1c;font-weight:700">{{ $message }}</p>@enderror
    @if ($canReactivate)
        <form method="post" action="{{ request()->fullUrl() }}">
            @csrf
            <button type="submit" class="btn">{{ __('نعم، أعد تفعيل :name', ['name' => $tenant->name]) }}</button>
        </form>
    @endif
@endsection
