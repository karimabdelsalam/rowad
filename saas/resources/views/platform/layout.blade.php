@php
    $rtl = app()->getLocale() === 'ar';
    $other = $rtl ? 'en' : 'ar';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('رواد — نظام إدارة الأملاك للمنشآت العقارية'))</title>
    <meta name="description" content="{{ __('نظام سحابي متكامل لإدارة العقارات والعقود والتحصيل وحسابات الملاك، مع بوابات للملاك والمستأجرين وموقع خاص لكل مؤسسة.') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #0f766e; --brand-dark: #115e59; --brand-ink: #134e4a; --brand-soft: #f0fdfa; --brand-line: #ccfbf1;
            --ink: #0f172a; --body: #334155; --muted: #64748b; --line: #e2e8f0; --bg: #ffffff; --soft: #f8fafc; --accent: #eb6834;
            --radius: 16px; --shadow: 0 1px 2px rgb(15 23 42 / .04), 0 8px 24px rgb(15 23 42 / .06);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; font-family: 'Cairo', system-ui, sans-serif; color: var(--body); background: var(--bg); line-height: 1.7; font-size: 16px; }
        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }
        h1, h2, h3 { color: var(--ink); line-height: 1.35; margin: 0; }
        .container { width: min(1180px, 100% - 32px); margin-inline: auto; }
        .icon { width: 22px; height: 22px; flex: none; }

        /* header */
        header.top { position: sticky; top: 0; z-index: 20; background: rgb(255 255 255 / .9); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        header.top .bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 70px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.35rem; color: var(--ink); }
        .brand-mark { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; color: #fff;
            background: linear-gradient(135deg, #14b8a6, var(--brand) 55%, var(--brand-ink)); box-shadow: 0 6px 16px rgb(15 118 110 / .3); }
        nav.links { display: flex; align-items: center; gap: 26px; font-weight: 600; color: var(--body); }
        nav.links a:hover { color: var(--brand); }
        .actions { display: flex; align-items: center; gap: 10px; }
        .lang { font-weight: 700; color: var(--muted); padding: 6px 10px; border-radius: 8px; }
        .lang:hover { background: var(--soft); }

        /* buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 20px; border-radius: 12px; border: 1px solid transparent;
            font: inherit; font-weight: 700; cursor: pointer; transition: transform .15s ease, box-shadow .15s ease, background .15s ease; white-space: nowrap; }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: var(--brand); color: #fff; box-shadow: 0 8px 20px rgb(15 118 110 / .28); }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-ghost { background: #fff; border-color: var(--line); color: var(--ink); }
        .btn-light { background: #fff; color: var(--brand-ink); }
        .btn-lg { padding: 14px 26px; font-size: 1.05rem; border-radius: 14px; }

        /* sections */
        section { padding: 88px 0; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: .85rem; font-weight: 700; color: var(--brand); background: var(--brand-soft);
            border: 1px solid var(--brand-line); padding: 4px 12px; border-radius: 999px; }
        .section-head { text-align: center; max-width: 720px; margin: 0 auto 48px; }
        .section-head h2 { font-size: clamp(1.7rem, 3.2vw, 2.4rem); font-weight: 800; margin: 14px 0 10px; }
        .section-head p { color: var(--muted); font-size: 1.08rem; margin: 0; }
        .alt { background: var(--soft); border-block: 1px solid var(--line); }

        /* forms */
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label { font-weight: 700; color: var(--ink); font-size: .95rem; }
        .field .hint { color: var(--muted); font-size: .85rem; }
        .input { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; font: inherit; background: #fff; color: var(--ink); transition: border-color .15s, box-shadow .15s; }
        .input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px rgb(15 118 110 / .12); }
        .input.invalid { border-color: #dc2626; }
        .error { color: #b91c1c; font-size: .87rem; font-weight: 600; }
        .hp { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }

        footer.site { background: #0b1324; color: #94a3b8; padding: 56px 0 28px; }
        footer.site .cols { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 32px; }
        footer.site h4 { color: #fff; margin: 0 0 12px; font-size: 1rem; }
        footer.site a:hover { color: #fff; }
        footer.site ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        footer.site .bottom { border-top: 1px solid #1e293b; margin-top: 36px; padding-top: 20px; font-size: .9rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }

        @media (max-width: 900px) {
            nav.links { display: none; }
            section { padding: 64px 0; }
            footer.site .cols { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .actions .btn-ghost { display: none; }
        }
        @yield('styles')
    </style>
</head>
<body>
    <header class="top">
        <div class="container bar">
            <a href="{{ route('central.home', [], false) }}" class="brand" aria-label="{{ __('رواد') }}">
                <span class="brand-mark"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M4.5 21V9.75L12 4.5l7.5 5.25V21M9.75 21v-6h4.5v6"/></svg></span>
                {{ __('رواد') }}
            </a>
            <nav class="links">
                <a href="{{ route('central.home', [], false) }}#features">{{ __('المزايا') }}</a>
                <a href="{{ route('central.home', [], false) }}#portals">{{ __('البوابات') }}</a>
                <a href="{{ route('central.home', [], false) }}#pricing">{{ __('الأسعار') }}</a>
                <a href="{{ route('central.home', [], false) }}#faq">{{ __('الأسئلة الشائعة') }}</a>
            </nav>
            <div class="actions">
                <a class="lang" href="{{ route('locale.switch', $other, false) }}">{{ $rtl ? 'English' : 'العربية' }}</a>
                <a class="btn btn-primary" href="{{ route('central.signup', [], false) }}">{{ __('جرّب مجانًا') }}</a>
            </div>
        </div>
    </header>

    @yield('content')

    <footer class="site">
        <div class="container">
            <div class="cols">
                <div>
                    <a href="{{ route('central.home', [], false) }}" class="brand" style="color:#fff"><span class="brand-mark"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M4.5 21V9.75L12 4.5l7.5 5.25V21M9.75 21v-6h4.5v6"/></svg></span>{{ __('رواد') }}</a>
                    <p style="max-width:420px">{{ __('نظام سحابي لإدارة الأملاك صُمم للمنشآت العقارية في السعودية: العقود والتحصيل وحسابات الملاك والفوترة الإلكترونية في مكان واحد.') }}</p>
                </div>
                <div>
                    <h4>{{ __('المنصة') }}</h4>
                    <ul>
                        <li><a href="{{ route('central.home', [], false) }}#features">{{ __('المزايا') }}</a></li>
                        <li><a href="{{ route('central.home', [], false) }}#pricing">{{ __('الأسعار') }}</a></li>
                        <li><a href="{{ route('central.signup', [], false) }}">{{ __('إنشاء حساب مؤسسة') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h4>{{ __('الدعم') }}</h4>
                    <ul>
                        <li><a href="{{ route('central.home', [], false) }}#faq">{{ __('الأسئلة الشائعة') }}</a></li>
                        @if (filled(config('mail.from.address')) && ! str_ends_with((string) config('mail.from.address'), '@example.com'))
                            <li><a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a></li>
                        @endif
                    </ul>
                </div>
            </div>
            <div class="bottom">
                <span>© {{ now()->year }} {{ __('رواد') }}. {{ __('جميع الحقوق محفوظة.') }}</span>
                <span>{{ __('صُنع للمنشآت العقارية في المملكة العربية السعودية') }}</span>
            </div>
        </div>
    </footer>
    @yield('scripts')
</body>
</html>
