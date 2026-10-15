@extends('errors.layout')
@php($ar = app()->getLocale() !== 'en')
@section('code', $ar ? 'الترخيص' : 'License')
@section('title', $ar ? 'النظام يحتاج ترخيصًا صالحًا' : 'A valid license is required')
@section('message', $reason.' '.($ar ? 'يستطيع مدير النظام تفعيله من صفحة الترخيص.' : 'The system administrator can activate it from the license page.'))
@section('actions')
    <a class="btn primary" href="/platform/license">{{ $ar ? 'صفحة الترخيص' : 'License page' }}</a>
@endsection
