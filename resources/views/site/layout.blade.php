@php
    use App\Support\Settings;
    use App\Support\Website;
    $rtl = app()->getLocale() === 'ar';
    $primary = Settings::get('landing_primary_color') ?: '#0f766e';
    $whatsapp = preg_replace('/\D/', '', (string) Settings::get('landing_whatsapp'));
    $logo = Settings::get('logo');
    $phone = Settings::get('mobile') ?: Settings::get('phone');
    $email = Settings::get('email');
    $address = trim(Website::text('address1').' '.Website::text('address2'));
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.favicons')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', Website::name())</title>
    <meta name="description" content="@yield('description', Website::text('landing_hero_subtitle'))">
    <meta property="og:site_name" content="{{ Website::name() }}">
    <meta property="og:title" content="@yield('title', Website::name())">
    <meta property="og:description" content="@yield('description', Website::text('landing_hero_subtitle'))">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    <meta name="theme-color" content="{{ $primary }}">
    <link href="{{ asset('fonts/cairo/cairo.css') }}" rel="stylesheet">
    <style>
        :root {
            --primary: {{ $primary }};
            --primary-dark: color-mix(in srgb, var(--primary) 70%, #020617);
            --primary-soft: color-mix(in srgb, var(--primary) 9%, #fff);
            --primary-line: color-mix(in srgb, var(--primary) 22%, #fff);
            --ink: #0f172a; --ink-2: #334155; --muted: #64748b; --line: #e5e9f0; --bg: #f6f8fa; --card: #fff;
            --sale: #b45309; --sale-soft: #fff7ed;
            --radius: 18px; --shadow: 0 1px 2px rgb(15 23 42 / .04), 0 8px 24px rgb(15 23 42 / .06);
            --shadow-lg: 0 24px 60px rgb(15 23 42 / .14);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        [id] { scroll-margin-top: 88px; } /* anchors land below the sticky header */
        body { margin: 0; font-family: 'Cairo', system-ui, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.65; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }
        .ic { width: 1.15em; height: 1.15em; flex: none; }
        .container { width: min(1200px, 100% - 32px); margin-inline: auto; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }

        /* header */
        header.top { position: sticky; top: 0; z-index: 30; background: rgb(255 255 255 / .92); backdrop-filter: saturate(1.4) blur(12px); border-bottom: 1px solid rgb(15 23 42 / .06); }
        header.top .bar { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 72px; }
        .brand { display: flex; align-items: center; gap: 12px; font-weight: 800; font-size: 1.15rem; line-height: 1.25; }
        .brand img { height: 44px; width: auto; max-width: 160px; object-fit: contain; }
        .brand .mono { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: var(--primary); color: #fff; font-size: 1.1rem; }
        nav.links { display: flex; align-items: center; gap: 4px; font-weight: 600; font-size: .95rem; }
        nav.links > a:not(.btn) { padding: 8px 12px; border-radius: 10px; color: var(--ink-2); white-space: nowrap; }
        nav.links > a:not(.btn):hover { background: var(--primary-soft); color: var(--primary); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 20px; border-radius: 12px; border: 1px solid transparent; font: inherit; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background .15s, box-shadow .15s, transform .15s; }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 6px 16px color-mix(in srgb, var(--primary) 30%, transparent); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { border-color: var(--line); background: var(--card); color: var(--ink); }
        .btn-outline:hover { border-color: var(--primary-line); color: var(--primary); }
        .btn-wa { background: #25d366; color: #fff; } .btn-wa:hover { background: #1fb558; }
        .btn-block { width: 100%; }

        /* hero */
        .hero { position: relative; color: #fff; padding: 88px 0 132px; overflow: hidden;
            background: radial-gradient(900px 420px at 85% -10%, color-mix(in srgb, var(--primary) 55%, #fff) 0%, transparent 60%), linear-gradient(135deg, var(--primary-dark), #0b1220); }
        .hero__img { position: absolute; inset: 0; background: center / cover no-repeat; transform: scale(1.03); }
        .hero__img::after { content: ""; position: absolute; inset: 0;
            background: linear-gradient({{ $rtl ? '270deg' : '90deg' }}, rgb(2 6 23 / .82) 0%, rgb(2 6 23 / .55) 45%, rgb(2 6 23 / .25) 100%), linear-gradient(0deg, rgb(2 6 23 / .55), transparent 45%); }
        .hero .container { position: relative; }
        .hero__kicker { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: rgb(255 255 255 / .14); border: 1px solid rgb(255 255 255 / .2); font-weight: 700; font-size: .85rem; backdrop-filter: blur(6px); }
        .hero h1 { font-size: clamp(2rem, 4.4vw, 3.3rem); line-height: 1.2; margin: 18px 0 12px; max-width: 24ch; font-weight: 800; text-wrap: balance; }
        .hero p.lead { margin: 0; max-width: 46ch; font-size: clamp(1rem, 1.6vw, 1.2rem); color: rgb(255 255 255 / .86); }
        .hero__stats { display: flex; flex-wrap: wrap; gap: 28px; margin-top: 30px; }
        .hero__stats b { display: block; font-size: 1.7rem; font-weight: 800; line-height: 1.1; }
        .hero__stats span { font-size: .85rem; color: rgb(255 255 255 / .75); }

        /* search */
        .search { position: relative; z-index: 2; margin-top: -84px; background: var(--card); border-radius: 22px; padding: 10px 16px 16px; box-shadow: var(--shadow-lg); }
        .search__tabs { display: flex; gap: 4px; padding: 6px 0 12px; border-bottom: 1px solid var(--line); margin-bottom: 14px; }
        .search__tabs label { cursor: pointer; }
        .search__tabs input { position: absolute; opacity: 0; pointer-events: none; }
        .search__tabs span { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 999px; font-weight: 700; color: var(--muted); }
        .search__tabs span small { font-weight: 600; font-size: .75rem; padding: 0 7px; border-radius: 999px; background: var(--bg); color: var(--muted); }
        .search__tabs input:checked + span { background: var(--primary-soft); color: var(--primary); }
        .search__tabs input:checked + span small { background: #fff; color: var(--primary); }
        .search__tabs input:focus-visible + span { outline: 2px solid var(--primary); outline-offset: 2px; }
        .search__fields { display: grid; grid-template-columns: 1.4fr 1fr 1fr .8fr 1fr auto; gap: 10px; align-items: end; }
        .field { display: grid; gap: 4px; }
        .field > span { font-size: .78rem; font-weight: 700; color: var(--muted); padding-inline-start: 4px; }
        .field input, .field select, .field textarea { width: 100%; padding: 11px 14px; border: 1px solid var(--line); border-radius: 12px; font: inherit; background: #fff; color: var(--ink); transition: border-color .15s, box-shadow .15s; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-soft); }
        .search .btn { height: 48px; padding-inline: 26px; }

        /* sections */
        section { padding: 56px 0; }
        .section-head { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 12px 24px; margin-bottom: 24px; }
        .eyebrow { color: var(--primary); font-weight: 800; font-size: .85rem; letter-spacing: .02em; margin: 0 0 4px; }
        h2.section-title { font-size: clamp(1.4rem, 2.6vw, 2rem); line-height: 1.3; margin: 0; font-weight: 800; }
        .section-sub { margin: 6px 0 0; color: var(--muted); }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; border: 1px solid var(--line); background: #fff; font-weight: 700; font-size: .88rem; color: var(--ink-2); }
        .chip.is-active { background: var(--ink); border-color: var(--ink); color: #fff; }
        .chip small { opacity: .7; font-weight: 600; }
        .sort select { padding: 7px 12px; border-radius: 999px; border: 1px solid var(--line); font: inherit; font-weight: 600; font-size: .88rem; background: #fff; }

        /* unit cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 22px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); overflow: hidden; display: flex; flex-direction: column; transition: box-shadow .25s, transform .25s, border-color .25s; }
        .card:hover { box-shadow: var(--shadow-lg); transform: translateY(-4px); border-color: transparent; }
        .card__photo { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: linear-gradient(135deg, var(--primary-soft), #e2e8f0); }
        .card__photo img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s; }
        .card:hover .card__photo img { transform: scale(1.06); }
        .card__photo .ph { position: absolute; inset: 0; display: grid; place-items: center; color: var(--primary-line); }
        .card__photo .ph .ic { width: 64px; height: 64px; }
        .card__tags { position: absolute; inset-block-start: 12px; inset-inline: 12px; display: flex; justify-content: space-between; gap: 8px; }
        .tag { display: inline-flex; align-items: center; gap: 4px; font-size: .78rem; font-weight: 800; padding: 4px 11px; border-radius: 999px; background: rgb(255 255 255 / .94); color: var(--primary); box-shadow: 0 2px 8px rgb(0 0 0 / .12); }
        .tag.is-sale { color: var(--sale); }
        .tag.is-featured { background: #f59e0b; color: #fff; }
        .tag .ic { width: 14px; height: 14px; }
        .card__count { position: absolute; inset-block-end: 12px; inset-inline-end: 12px; display: inline-flex; align-items: center; gap: 4px; font-size: .75rem; font-weight: 700; color: #fff; background: rgb(15 23 42 / .55); padding: 3px 9px; border-radius: 999px; backdrop-filter: blur(4px); }
        .card__count .ic { width: 14px; height: 14px; }
        .card__body { padding: 16px 18px 18px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .card h3 { margin: 0; font-size: 1.08rem; font-weight: 800; line-height: 1.4; }
        .loc { display: flex; align-items: center; gap: 5px; color: var(--muted); font-size: .9rem; }
        .feats { display: flex; flex-wrap: wrap; gap: 6px 16px; color: var(--ink-2); font-size: .88rem; font-weight: 600; padding: 10px 0 2px; }
        .feats span { display: inline-flex; align-items: center; gap: 6px; }
        .feats .ic { color: var(--primary); }
        .card__foot { margin-top: auto; padding-top: 12px; border-top: 1px dashed var(--line); display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .price { font-weight: 800; color: var(--ink); font-size: 1.2rem; line-height: 1.2; }
        .price small { font-size: .78rem; font-weight: 600; color: var(--muted); }
        .price.is-ask { font-size: .95rem; color: var(--primary); }
        .card__more { display: inline-flex; align-items: center; gap: 4px; font-weight: 700; font-size: .85rem; color: var(--primary); }
        .card__more .ic { width: 16px; height: 16px; {{ $rtl ? '' : 'transform: scaleX(-1);' }} }
        .empty { text-align: center; color: var(--muted); padding: 56px 16px; background: #fff; border: 1px dashed var(--line); border-radius: var(--radius); }
        .empty .ic { width: 40px; height: 40px; margin: 0 auto 8px; color: var(--primary-line); }

        /* why us */
        .why { background: #fff; border-block: 1px solid var(--line); }
        .why__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 18px; }
        .why__item { padding: 22px; border-radius: var(--radius); background: var(--bg); border: 1px solid var(--line); }
        .why__item .ic { width: 44px; height: 44px; padding: 10px; border-radius: 14px; background: var(--primary); color: #fff; margin-bottom: 14px; }
        .why__item h3 { margin: 0 0 4px; font-size: 1.05rem; }
        .why__item p { margin: 0; color: var(--muted); font-size: .92rem; }
        .about { margin-top: 28px; padding: 24px 28px; border-radius: var(--radius); background: var(--primary-soft); border: 1px solid var(--primary-line); color: var(--ink-2); white-space: pre-line; font-size: 1.02rem; }

        /* contact */
        .contact { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 24px; align-items: start; }
        .contact__cards { display: grid; gap: 12px; }
        .contact__card { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: #fff; border: 1px solid var(--line); border-radius: 16px; transition: border-color .15s; }
        a.contact__card:hover { border-color: var(--primary-line); }
        .contact__card .ic { width: 42px; height: 42px; padding: 10px; border-radius: 12px; background: var(--primary-soft); color: var(--primary); }
        .contact__card small { display: block; color: var(--muted); font-size: .8rem; }
        .contact__card b { font-size: 1rem; }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
        .panel h3 { margin: 0 0 14px; font-size: 1.15rem; }
        form.inquiry { display: grid; gap: 12px; }
        form.inquiry .two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .alert { padding: 12px 16px; border-radius: 12px; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; margin-bottom: 14px; font-weight: 600; }
        .error { color: #b91c1c; font-size: .9rem; }
        .hp { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; opacity: 0; }

        /* viewing booking (partials/viewing-form) */
        form.viewing { display: grid; gap: 12px; }
        .viewing__days { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: thin; }
        .viewing__day { flex: none; display: grid; gap: 0; text-align: center; min-width: 78px; padding: 8px 10px; border-radius: 12px; border: 1px solid var(--line); background: #fff; font: inherit; cursor: pointer; color: inherit; }
        .viewing__day span { font-size: .75rem; color: var(--muted); }
        .viewing__day b { font-size: .95rem; }
        .viewing__day.is-active { border-color: var(--primary); background: var(--primary-soft); }
        .viewing__day.is-active span { color: var(--primary); }
        .viewing__times { display: grid; grid-template-columns: repeat(auto-fill, minmax(78px, 1fr)); gap: 8px; }
        .viewing__time input { position: absolute; opacity: 0; pointer-events: none; }
        .viewing__time span { display: block; text-align: center; padding: 8px 4px; border-radius: 10px; border: 1px solid var(--line); font-weight: 700; cursor: pointer; background: #fff; }
        .viewing__time input:checked + span { background: var(--primary); border-color: var(--primary); color: #fff; }
        .viewing__time input:focus-visible + span { outline: 2px solid var(--primary); outline-offset: 2px; }
        .viewing__who { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .viewing__who input { width: 100%; padding: 11px 14px; border: 1px solid var(--line); border-radius: 12px; font: inherit; }
        .viewing__note { margin: 0; font-size: .8rem; color: var(--muted); }
        .viewing__heading { grid-column: 1 / -1; margin: 4px 0 0; font-weight: 700; font-size: .85rem; color: var(--muted); }
        form.viewing:not(.is-js) .viewing__days, form.viewing.is-js .viewing__heading { display: none; }
        .viewing__times[hidden] { display: none; }

        .pagination { display: flex; gap: 6px; justify-content: center; margin-top: 32px; flex-wrap: wrap; }
        .pagination a { min-width: 40px; text-align: center; padding: 7px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--card); font-weight: 700; }
        .pagination .active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* footer */
        footer.site { background: #0b1220; color: #cbd5e1; padding: 56px 0 0; margin-top: 24px; }
        footer.site .cols { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr; gap: 28px; }
        footer.site h4 { color: #fff; margin: 0 0 12px; font-size: 1rem; }
        footer.site ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; font-size: .92rem; }
        footer.site a:hover { color: #fff; }
        footer.site .about-mini { color: #94a3b8; font-size: .92rem; margin: 10px 0 0; max-width: 34ch; }
        footer.site .brand { color: #fff; }
        footer.site .bottom { margin-top: 40px; padding: 18px 0; border-top: 1px solid rgb(255 255 255 / .08); display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; font-size: .85rem; color: #94a3b8; }
        footer.site .bottom a { color: #5eead4; font-weight: 700; }
        .wa-float { position: fixed; inset-block-end: 22px; inset-inline-end: 22px; z-index: 40; background: #25d366; color: #fff; width: 58px; height: 58px; border-radius: 50%; display: grid; place-items: center; box-shadow: 0 10px 24px rgb(37 211 102 / .4); transition: transform .2s; }
        .wa-float:hover { transform: scale(1.06); }

        @media (max-width: 1024px) {
            .search__fields { grid-template-columns: 1fr 1fr 1fr; }
            .search__fields .field--q { grid-column: 1 / -1; }
            .contact { grid-template-columns: 1fr; }
            footer.site .cols { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            header.top .bar { min-height: 62px; gap: 8px; }
            .brand { font-size: 1rem; gap: 8px; } .brand img, .brand .mono { height: 38px; } .brand .mono { width: 38px; }
            header.top .brand.has-logo span { display: none; } /* the logo carries the name on small screens */
            nav.links .hide-sm { display: none; } nav.links .btn { padding: 8px 12px; font-size: .85rem; }
            .hero { padding: 48px 0 112px; } .hero__stats { gap: 18px; } .hero__stats b { font-size: 1.35rem; }
            .search { padding: 8px 12px 12px; border-radius: 18px; }
            .search__fields { grid-template-columns: 1fr 1fr; }
            .search__fields .btn { grid-column: 1 / -1; }
            section { padding: 40px 0; }
            form.inquiry .two, .viewing__who { grid-template-columns: 1fr; }
            footer.site .cols { grid-template-columns: 1fr; }
            .wa-float { inset-block-end: 84px; }
        }
    </style>
    @stack('head')
</head>
<body>
<header class="top">
    <div class="container bar">
        <a href="{{ route('site.home') }}" @class(['brand', 'has-logo' => $logo])>
            @if ($logo)<img src="{{ route('site.logo') }}" alt="{{ Website::name() }}">@else<span class="mono">{{ mb_substr(Website::name(), 0, 1) }}</span>@endif
            <span>{{ Website::name() }}</span>
        </a>
        <nav class="links">
            <a href="{{ route('site.home', ['offer' => 'rent']) }}#units" class="hide-sm">{{ __('للإيجار') }}</a>
            <a href="{{ route('site.home', ['offer' => 'sale']) }}#units" class="hide-sm">{{ __('للبيع') }}</a>
            <a href="{{ route('site.home') }}#contact" class="hide-sm">{{ __('تواصل معنا') }}</a>
            <a href="{{ route('locale.switch', $rtl ? 'en' : 'ar') }}">{{ $rtl ? 'EN' : 'ع' }}</a>
            <a href="/client" class="btn btn-outline">@include('site.partials.icon', ['name' => 'user']){{ __('دخول العملاء') }}</a>
        </nav>
    </div>
</header>

@yield('content')

<footer class="site">
    <div class="container cols">
        <div>
            <a href="{{ route('site.home') }}" class="brand">
                @if ($logo)<img src="{{ route('site.logo') }}" alt="" style="background:#fff;border-radius:10px;padding:4px">@else<span class="mono">{{ mb_substr(Website::name(), 0, 1) }}</span>@endif
                <span>{{ Website::name() }}</span>
            </a>
            @if ($about = Website::text('landing_about'))<p class="about-mini">{{ \Illuminate\Support\Str::limit($about, 140) }}</p>@endif
        </div>
        <div>
            <h4>{{ __('الوحدات') }}</h4>
            <ul>
                <li><a href="{{ route('site.home', ['offer' => 'rent']) }}#units">{{ __('وحدات للإيجار') }}</a></li>
                <li><a href="{{ route('site.home', ['offer' => 'sale']) }}#units">{{ __('عقارات للبيع') }}</a></li>
                <li><a href="{{ route('site.home') }}#contact">{{ __('اطلب وحدة تناسبك') }}</a></li>
            </ul>
        </div>
        <div>
            <h4>{{ __('البوابات') }}</h4>
            <ul>
                <li><a href="/client">{{ __('بوابة المستأجرين') }}</a></li>
                <li><a href="/owner">{{ __('بوابة الملاك') }}</a></li>
            </ul>
        </div>
        <div>
            <h4>{{ __('تواصل معنا') }}</h4>
            <ul>
                @if ($phone)<li><a href="tel:{{ $phone }}" dir="ltr">{{ $phone }}</a></li>@endif
                @if ($email)<li><a href="mailto:{{ $email }}">{{ $email }}</a></li>@endif
                @if ($address)<li>{{ $address }}</li>@endif
            </ul>
        </div>
    </div>
    <div class="container bottom">
        <span>&copy; {{ now()->year }} {{ Website::name() }}</span>
        <span>@include('partials.powered-by')</span>
    </div>
</footer>

@if ($whatsapp)
    <a class="wa-float" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" aria-label="WhatsApp">@include('site.partials.whatsapp-icon', ['size' => 30])</a>
@endif
@stack('scripts')
</body>
</html>
