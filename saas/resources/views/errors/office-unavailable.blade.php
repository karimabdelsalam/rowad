@extends('errors.layout')
@php($ar = app()->getLocale() !== 'en')
@section('code', $tenant->name)
@section('title', $ar ? 'الخدمة متوقفة مؤقتًا' : 'Service paused')
@section('message', __('الخدمة متوقفة مؤقتًا لهذه المؤسسة. يرجى التواصل مع إدارة المنصة لتجديد الاشتراك.'))
@section('actions')@endsection
