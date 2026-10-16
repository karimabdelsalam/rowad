@php($ar = app()->getLocale() === 'ar')
<aside class="rowad-login-aside rowad-login-aside--platform">
    <div class="rowad-login-aside__brand">
        <span class="rowad-plat-mark"><img src="{{ asset('images/brand/mark.svg') }}" alt=""></span>
        <h1>@if ($ar)عقار <span>نوفا</span>@else Aqar <span>Nova</span>@endif</h1>
        <p>{{ __('لوحة إدارة المنصة') }}</p>
    </div>
    <ul class="rowad-login-aside__points">
        @foreach (['المؤسسات والاشتراكات والفوترة', 'الباقات والحدود واستخدام الذكاء الاصطناعي', 'صحة النظام والتراخيص والإعدادات'] as $point)
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>{{ __($point) }}</li>
        @endforeach
    </ul>
    <p class="rowad-plat-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
        {{ __('هذه اللوحة لإدارة المنصة. مستخدمو المؤسسات يدخلون من رابط مؤسستهم.') }}
    </p>
    <a class="rowad-login-aside__site" href="{{ url('/') }}">{{ __('الموقع الرئيسي') }} ↗</a>
</aside>
