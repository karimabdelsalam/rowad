<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') | {{ settings('brand_name', 'Planova بلانوفا') }}</title>
<link rel="stylesheet" href="{{ asset('assets/console.css') }}">
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">💼 {{ settings('brand_name', 'Planova بلانوفا') }}</div>
        <nav>
            @foreach ([
                ['dashboard', 'لوحة التحكم', '📊'],
                ['clinics.index', 'العيادات', '🏥'],
                ['tenants.index', 'عيادات SaaS', '🏢'],
                ['invoices.index', 'الفواتير', '🧾'],
                ['payments.index', 'المدفوعات', '💳'],
                ['plans.index', 'خطط الاشتراك', '📦'],
                ['log.index', 'سجل النشاط', '📜'],
                ['users.index', 'المستخدمون', '👤'],
                ['settings.edit', 'الإعدادات', '⚙️'],
            ] as [$route, $label, $icon])
                @if (Route::has($route))
                    <a href="{{ route($route) }}" @class(['active' => request()->routeIs($route)])>
                        <span class="ico">{{ $icon }}</span> {{ $label }}</a>
                @endif
            @endforeach
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <h1>@yield('title')</h1>
            <div class="userbox">
                <span><strong>{{ auth()->user()?->name }}</strong></span>
                <form method="post" action="{{ route('logout') }}">@csrf
                    <button class="btn btn-light btn-sm" type="submit">خروج</button>
                </form>
            </div>
        </header>

        <main class="content">
            @foreach (['success' => 'success', 'warning' => 'warning', 'danger' => 'danger'] as $key => $type)
                @if (session($key))
                    <div class="alert alert-{{ $type }}">{{ session($key) }}</div>
                @endif
            @endforeach

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error){{ $error }}@endforeach
                </div>
            @endif

            @yield('content')
        </main>
    </div>

</div>
</body>
</html>
