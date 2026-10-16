@php
    use App\Support\Branding;
    $logo = Branding::logoUrl();
    $name = Branding::name();
    $panel = filament()->getCurrentPanel()?->getId();
    $points = match ($panel) {
        'owner' => ['كشف حساب أملاكك لحظيًا', 'تحويلاتك ومستحقاتك بالتفصيل', 'عقود وحداتك ومستأجريها'],
        'client' => ['دفعاتك القادمة والمتأخرة', 'نسخة من عقدك وسنداتك', 'طلبات الصيانة والدعم'],
        default => ['الوحدات والعقود والتحصيل', 'حسابات الملاك والفوترة الإلكترونية', 'تقارير لحظية ومساعد ذكي'],
    };
    $site = \App\Support\Website::enabled() ? route('site.home') : null;
@endphp
<aside class="rowad-login-aside">
    <div class="rowad-login-aside__brand">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $name }}">
        @else
            <span class="rowad-brand__mark rowad-brand__mark--lg" aria-hidden="true">{{ Branding::initials() }}</span>
        @endif
        <h1>{{ $name }}</h1>
        <p>{{ Branding::panelTitle($panel) }}</p>
    </div>
    <ul class="rowad-login-aside__points">
        @foreach ($points as $point)
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>{{ __($point) }}</li>
        @endforeach
    </ul>
    @if ($site)
        <a class="rowad-login-aside__site" href="{{ $site }}">{{ __('موقع المؤسسة') }} ↗</a>
    @endif
</aside>
