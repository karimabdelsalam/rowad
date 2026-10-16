{{-- Error and maintenance pages. No database or session calls: they must render even when those are down. --}}
@php($rtl = app()->getLocale() !== 'en')
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ $rtl ? 'عقار نوفا' : 'Aqar Nova' }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font-family: 'Cairo', system-ui, sans-serif; color: #0f172a;
            background: radial-gradient(800px 360px at 50% 0%, rgb(20 184 166 / .14), transparent 60%), #f8fafc; }
        main { width: min(520px, 100%); background: #fff; border: 1px solid #e2e8f0; border-radius: 22px; padding: 40px 32px; text-align: center;
            box-shadow: 0 1px 2px rgb(15 23 42 / .04), 0 12px 32px rgb(15 23 42 / .08); }
        .mark { height: 44px; width: auto; margin-bottom: 18px; }
        .code { display: inline-block; font-size: .85rem; font-weight: 800; color: #0f766e; background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 999px; padding: 2px 12px; }
        h1 { font-size: 1.6rem; margin: 14px 0 8px; }
        p { color: #64748b; margin: 0 0 24px; line-height: 1.8; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        a.btn, button.btn { display: inline-block; padding: 10px 20px; border-radius: 12px; font-weight: 700; text-decoration: none; font: inherit; font-weight: 700; cursor: pointer; border: 0; }
        a.primary { background: #0f766e; color: #fff; }
        a.ghost { border: 1px solid #e2e8f0; color: #0f172a; }
        .credit { margin-top: 26px; font-size: .8rem; color: #94a3b8; }
        .credit a { color: #0f766e; font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
<main>
    <img class="mark" src="/images/brand/mark.svg" alt="">
    <div><span class="code">@yield('code')</span></div>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <div class="actions">
        @section('actions')
            <a class="btn primary" href="/">{{ $rtl ? 'الصفحة الرئيسية' : 'Home' }}</a>
            <a class="btn ghost" href="javascript:history.back()">{{ $rtl ? 'رجوع' : 'Back' }}</a>
        @show
    </div>
    <div class="credit">@include('partials.powered-by')</div>
</main>
</body>
</html>
