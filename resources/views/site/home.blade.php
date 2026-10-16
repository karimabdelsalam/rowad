@extends('site.layout')

@php
    use App\Support\Settings;
    use App\Support\Website;
    $offer = $filters['offer'] ?? null;
    $phone = Settings::get('mobile') ?: Settings::get('phone');
    $email = Settings::get('email');
    $whatsapp = preg_replace('/\D/', '', (string) Settings::get('landing_whatsapp'));
    $address = trim(Website::text('address1').' '.Website::text('address2'));
    $query = fn (array $change) => route('site.home', array_filter(array_merge($filters, $change), fn ($v) => filled($v))).'#units';
@endphp

@if ($hero)
    @section('og_image', $hero)
@endif

@section('content')
<div class="hero">
    @if ($hero)<div class="hero__img" style="background-image:url('{{ $hero }}')"></div>@endif
    <div class="container">
        <span class="hero__kicker">@include('site.partials.icon', ['name' => 'pin']){{ $cities->count() === 1 ? __('عقارات في :city', ['city' => $cities->first()->name]) : __('عقارات للإيجار والبيع') }}</span>
        <h1>{{ Website::text('landing_hero_title') ?: __('وحدات للإيجار والبيع بإدارة موثوقة') }}</h1>
        <p class="lead">{{ Website::text('landing_hero_subtitle') ?: __('تصفح الوحدات المتاحة الآن، واحجز موعد معاينة أونلاين في دقيقة.') }}</p>
        @if ($totalListed > 0)
            <div class="hero__stats">
                <div><b>{{ number_format($totalListed) }}</b><span>{{ __('وحدة متاحة الآن') }}</span></div>
                @if ($counts['rent'] && $counts['sale'])
                    <div><b>{{ number_format($counts['rent']) }}</b><span>{{ __('للإيجار') }}</span></div>
                    <div><b>{{ number_format($counts['sale']) }}</b><span>{{ __('للبيع') }}</span></div>
                @endif
                @if ($cities->count() > 1)<div><b>{{ $cities->count() }}</b><span>{{ __('مدن') }}</span></div>@endif
            </div>
        @endif
    </div>
</div>

<div class="container">
    <form class="search" method="get" action="{{ route('site.home') }}#units" role="search">
        <div class="search__tabs" role="radiogroup" aria-label="{{ __('نوع العرض') }}">
            @foreach (['' => [__('الكل'), $totalListed], 'rent' => [__('للإيجار'), $counts['rent']], 'sale' => [__('للبيع'), $counts['sale']]] as $value => [$label, $n])
                <label><input type="radio" name="offer" value="{{ $value }}" @checked(($offer ?? '') === $value)><span>{{ $label }} <small>{{ $n }}</small></span></label>
            @endforeach
        </div>
        <div class="search__fields">
            <label class="field field--q"><span>{{ __('ابحث') }}</span><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('اسم الوحدة أو المبنى') }}"></label>
            <label class="field"><span>{{ __('المدينة') }}</span>
                <select name="city">
                    <option value="">{{ __('كل المدن') }}</option>
                    @foreach ($cities as $city)<option value="{{ $city->id }}" @selected(($filters['city'] ?? null) == $city->id)>{{ $city->name }}</option>@endforeach
                </select>
            </label>
            <label class="field"><span>{{ __('نوع العقار') }}</span>
                <select name="category">
                    <option value="">{{ __('كل الأنواع') }}</option>
                    @foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ __($category->title) }}</option>@endforeach
                </select>
            </label>
            <label class="field"><span>{{ __('الغرف') }}</span>
                <select name="rooms">
                    <option value="">{{ __('أي عدد') }}</option>
                    @foreach ([1, 2, 3, 4, 5] as $n)<option value="{{ $n }}" @selected(($filters['rooms'] ?? null) == $n)>{{ $n }}+</option>@endforeach
                </select>
            </label>
            <label class="field"><span>{{ __('أقصى سعر') }}</span><input type="number" name="max_price" min="0" step="1000" inputmode="numeric" value="{{ $filters['max_price'] ?? '' }}" placeholder="{{ __('بلا حد') }}"></label>
            <button class="btn btn-primary" type="submit">@include('site.partials.icon', ['name' => 'search', 'stroke' => 2]){{ __('بحث') }}</button>
        </div>
        @if (filled($filters['sort'] ?? null))<input type="hidden" name="sort" value="{{ $filters['sort'] }}">@endif
    </form>
</div>

<section id="units">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow">{{ __('الوحدات المتاحة') }}</p>
                <h2 class="section-title">{{ match ($offer) { 'rent' => __('وحدات للإيجار'), 'sale' => __('عقارات للبيع'), default => __('اختر وحدتك القادمة') } }}</h2>
                <p class="section-sub">{{ trans_choice('{0} لا توجد نتائج|{1} نتيجة واحدة|{2} نتيجتان|[3,10] :count نتائج|[11,*] :count نتيجة', $units->total()) }}</p>
            </div>
            <div class="chips">
                @foreach (['' => __('الكل'), 'rent' => __('للإيجار'), 'sale' => __('للبيع')] as $value => $label)
                    <a href="{{ $query(['offer' => $value ?: null, 'page' => null]) }}" @class(['chip', 'is-active' => ($offer ?? '') === $value])>{{ $label }}</a>
                @endforeach
                <form class="sort" method="get" action="{{ route('site.home') }}#units">
                    @foreach (array_filter(\Illuminate\Support\Arr::except($filters, ['sort'])) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <label class="sr-only" for="sort">{{ __('الترتيب') }}</label>
                    <select id="sort" name="sort" onchange="this.form.submit()">
                        <option value="">{{ __('الأحدث') }}</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>{{ __('السعر: الأقل أولًا') }}</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>{{ __('السعر: الأعلى أولًا') }}</option>
                    </select>
                </form>
            </div>
        </div>

        @if ($units->isEmpty())
            <div class="empty">
                @include('site.partials.icon', ['name' => 'search'])
                <div>{{ $totalListed ? __('لا توجد وحدات مطابقة لبحثك') : __('لا توجد وحدات متاحة حاليًا') }}</div>
                @if ($totalListed)<p><a class="btn btn-outline" href="{{ route('site.home') }}#units">{{ __('عرض كل الوحدات') }}</a></p>@endif
            </div>
        @else
            <div class="grid">
                @foreach ($units as $unit)
                    @include('site.partials.unit-card', ['unit' => $unit])
                @endforeach
            </div>
            @if ($units->hasPages())
                <nav class="pagination" aria-label="{{ __('الصفحات') }}">
                    @foreach ($units->getUrlRange(1, $units->lastPage()) as $page => $url)
                        <a href="{{ $url }}#units" @class(['active' => $page === $units->currentPage()])>{{ $page }}</a>
                    @endforeach
                </nav>
            @endif
        @endif
    </div>
</section>

<section class="why">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow">{{ __('لماذا نحن') }}</p>
                <h2 class="section-title">{{ __('تجربة إيجار وشراء بلا تعقيد') }}</h2>
            </div>
        </div>
        <div class="why__grid">
            @foreach ([
                ['calendar', 'معاينة بموعد', 'اختر اليوم والساعة المناسبين لك من صفحة الوحدة، ويصلك التأكيد برسالة.'],
                ['shield', 'عقود موثقة', 'عقود واضحة البنود وموثقة إلكترونيًا، مع توقيع العقد من جوالك.'],
                ['device', 'بوابة إلكترونية', 'تابع دفعاتك وعقدك وسنداتك من بوابة المستأجر في أي وقت.'],
                ['wrench', 'صيانة ومتابعة', 'ارفع طلب الصيانة بالصور وتابع حالته حتى الإنجاز.'],
            ] as [$icon, $title, $text])
                <div class="why__item">
                    @include('site.partials.icon', ['name' => $icon])
                    <h3>{{ __($title) }}</h3>
                    <p>{{ __($text) }}</p>
                </div>
            @endforeach
        </div>
        @if ($about = Website::text('landing_about'))
            <div class="about" id="about">{{ $about }}</div>
        @endif
    </div>
</section>

<section id="contact">
    <div class="container">
        <div class="section-head">
            <div>
                <p class="eyebrow">{{ __('تواصل معنا') }}</p>
                <h2 class="section-title">{{ __('لم تجد ما تبحث عنه؟') }}</h2>
                <p class="section-sub">{{ __('أخبرنا بما تحتاجه وسنرشح لك الوحدات المناسبة.') }}</p>
            </div>
        </div>
        <div class="contact">
            <div class="contact__cards">
                @if ($phone)
                    <a class="contact__card" href="tel:{{ $phone }}">@include('site.partials.icon', ['name' => 'phone'])<div><small>{{ __('اتصل بنا') }}</small><b dir="ltr">{{ $phone }}</b></div></a>
                @endif
                @if ($whatsapp)
                    <a class="contact__card" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">@include('site.partials.icon', ['name' => 'chat'])<div><small>{{ __('واتساب') }}</small><b>{{ __('راسلنا الآن') }}</b></div></a>
                @endif
                @if ($email)
                    <a class="contact__card" href="mailto:{{ $email }}">@include('site.partials.icon', ['name' => 'mail'])<div><small>{{ __('البريد الإلكتروني') }}</small><b>{{ $email }}</b></div></a>
                @endif
                @if ($address)
                    <div class="contact__card">@include('site.partials.icon', ['name' => 'pin'])<div><small>{{ __('العنوان') }}</small><b>{{ $address }}</b></div></div>
                @endif
            </div>
            <div class="panel">
                <h3>{{ __('أرسل رسالة') }}</h3>
                @include('site.partials.inquiry-form', ['action' => route('site.contact')])
            </div>
        </div>
    </div>
</section>
@endsection
