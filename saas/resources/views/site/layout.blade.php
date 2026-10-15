@php
    use App\Support\Settings;
    use App\Support\Website;
    $rtl = app()->getLocale() === 'ar';
    $primary = Settings::get('landing_primary_color', '#0f766e');
    $whatsapp = preg_replace('/\D/', '', (string) Settings::get('landing_whatsapp'));
    $logo = Settings::get('logo');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', Website::name())</title>
    <meta name="description" content="@yield('description', Website::text('landing_hero_subtitle'))">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700,800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: {{ $primary }}; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --bg: #f8fafc; --card: #fff; --radius: 14px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Tajawal', system-ui, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.6; }
        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }
        .container { width: min(1180px, 100% - 32px); margin-inline: auto; }
        header.top { background: var(--card); border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 10; }
        header.top .bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 68px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.2rem; }
        .brand img { height: 40px; width: auto; }
        nav.links { display: flex; align-items: center; gap: 18px; font-weight: 500; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: 1px solid transparent; font: inherit; font-weight: 700; cursor: pointer; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-outline { border-color: var(--line); background: var(--card); }
        .hero { background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 55%, #0f172a)); color: #fff; padding: 64px 0 88px; }
        .hero h1 { font-size: clamp(1.8rem, 4vw, 2.8rem); margin: 0 0 8px; }
        .hero p { margin: 0; opacity: .9; font-size: 1.1rem; }
        .search { background: var(--card); border-radius: var(--radius); padding: 16px; margin-top: -48px; box-shadow: 0 10px 30px rgb(15 23 42 / .08); display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .search input, .search select, form.inquiry input, form.inquiry textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font: inherit; background: #fff; }
        section { padding: 40px 0; }
        h2.section-title { font-size: 1.5rem; margin: 0 0 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 20px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); overflow: hidden; transition: box-shadow .2s, transform .2s; display: flex; flex-direction: column; }
        .card:hover { box-shadow: 0 12px 28px rgb(15 23 42 / .1); transform: translateY(-2px); }
        .card .photo { aspect-ratio: 4 / 3; background: #e2e8f0 center / cover no-repeat; position: relative; }
        .badge { position: absolute; inset-block-start: 12px; inset-inline-start: 12px; background: var(--primary); color: #fff; font-size: .8rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
        .card .body { padding: 14px 16px 18px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .card h3 { margin: 0; font-size: 1.1rem; }
        .meta { color: var(--muted); font-size: .92rem; display: flex; flex-wrap: wrap; gap: 4px 14px; }
        .price { font-weight: 800; color: var(--primary); font-size: 1.15rem; margin-top: auto; }
        .empty { text-align: center; color: var(--muted); padding: 48px 0; }
        .alert { padding: 12px 16px; border-radius: 10px; background: #ecfdf5; color: #065f46; margin-bottom: 16px; }
        .error { color: #b91c1c; font-size: .9rem; }
        .pagination { display: flex; gap: 6px; justify-content: center; margin-top: 28px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 6px 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--card); }
        .pagination .active { background: var(--primary); color: #fff; border-color: var(--primary); }
        footer { background: #0f172a; color: #cbd5e1; padding: 32px 0; margin-top: 40px; }
        footer .cols { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; }
        .wa { position: fixed; inset-block-end: 20px; inset-inline-end: 20px; background: #25d366; color: #fff; width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; box-shadow: 0 8px 20px rgb(0 0 0 / .2); font-weight: 800; }
        @media (max-width: 640px) { nav.links .hide-sm { display: none; } .hero { padding: 40px 0 72px; } }
    </style>
    @stack('head')
</head>
<body>
<header class="top">
    <div class="container bar">
        <a href="{{ route('site.home') }}" class="brand">
            @if ($logo)<img src="{{ $logo }}" alt="">@endif
            <span>{{ Website::name() }}</span>
        </a>
        <nav class="links">
            <a href="{{ route('site.home') }}#units" class="hide-sm">{{ __('الوحدات المتاحة') }}</a>
            <a href="{{ route('site.home') }}#contact" class="hide-sm">{{ __('تواصل معنا') }}</a>
            <a href="{{ route('locale.switch', $rtl ? 'en' : 'ar') }}">{{ $rtl ? 'English' : 'العربية' }}</a>
            <a href="/client" class="btn btn-outline">{{ __('دخول العملاء') }}</a>
        </nav>
    </div>
</header>

@yield('content')

<footer>
    <div class="container cols">
        <div>
            <strong>{{ Website::name() }}</strong>
            <div>{{ Website::text('address1') }}</div>
        </div>
        <div>
            @if ($phone = Settings::get('phone') ?: Settings::get('mobile'))<div>{{ __('هاتف') }}: <a href="tel:{{ $phone }}" dir="ltr">{{ $phone }}</a></div>@endif
            @if ($email = Settings::get('email'))<div>{{ __('البريد') }}: <a href="mailto:{{ $email }}">{{ $email }}</a></div>@endif
        </div>
        <div>&copy; {{ now()->year }} {{ Website::name() }}</div>
    </div>
</footer>

@if ($whatsapp)
    <a class="wa" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="WhatsApp">WA</a>
@endif
</body>
</html>
