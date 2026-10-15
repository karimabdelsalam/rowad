@extends('errors.layout')
@php($ar = app()->getLocale() !== 'en')
@section('code', '404')
@section('title', $ar ? 'لا توجد مؤسسة بهذا الرابط' : 'No organization at this address')
@section('message', $ar
    ? 'تأكد من كتابة رابط مؤسستك بشكل صحيح، أو أنشئ حساب مؤسسة جديدًا من عقار نوفا.'
    : 'Check the address of your organization, or create a new organization account on Aqar Nova.')
@section('actions')
    <a class="btn primary" href="{{ $home }}">{{ $ar ? 'الذهاب إلى عقار نوفا' : 'Go to Aqar Nova' }}</a>
    <a class="btn ghost" href="{{ $home }}/signup">{{ $ar ? 'إنشاء حساب مؤسسة' : 'Create an organization' }}</a>
@endsection
