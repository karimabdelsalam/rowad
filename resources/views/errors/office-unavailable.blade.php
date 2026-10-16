@extends('errors.layout')
@php($ar = app()->getLocale() !== 'en')
@php($cancelled = $tenant->status === \App\Models\Tenant::STATUS_CANCELLED)
@section('code', $tenant->name)
@section('title', $cancelled ? __('تم إلغاء الاشتراك') : ($ar ? 'الخدمة متوقفة مؤقتًا' : 'Service paused'))
@section('message')
    @if ($cancelled && $tenant->canBeReactivated())
        {{ __('أُلغي اشتراك المؤسسة بتاريخ :date. بياناتها محفوظة حتى :purge ويمكن إعادة تفعيلها من الرابط المرسل إلى بريد المؤسسة.', ['date' => $tenant->cancelled_at?->format('Y-m-d'), 'purge' => $tenant->purge_after->format('Y-m-d')]) }}
    @elseif ($cancelled)
        {{ __('أُلغي اشتراك هذه المؤسسة. للاستفسار تواصل مع إدارة المنصة.') }}
    @else
        {{ __('الخدمة متوقفة مؤقتًا لهذه المؤسسة. يرجى التواصل مع إدارة المنصة لتجديد الاشتراك.') }}
    @endif
@endsection
@section('actions')
    @if ($cancelled && $tenant->canBeReactivated())
        @if (session('resent'))
            <p style="color:#047857;font-weight:700">{{ __('أرسلنا رابط إعادة التفعيل إلى بريد المؤسسة.') }}</p>
        @else
            <form method="post" action="{{ route('office.reactivate.resend') }}">
                @csrf
                <button type="submit" class="btn">{{ __('إرسال رابط إعادة التفعيل') }}</button>
            </form>
        @endif
    @endif
@endsection
