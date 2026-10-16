@extends('site.layout')

@php
    use App\Support\Settings;
    $title = $unit->localized('title');
    $isRtl = app()->getLocale() === 'ar';
    $photos = $unit->getMedia('photos');
    $sale = $unit->offer_type === \App\Enums\OfferType::Sale;
    $price = $unit->publicPrice();
    $phone = Settings::get('mobile') ?: Settings::get('phone');
    $whatsapp = preg_replace('/\D/', '', (string) Settings::get('landing_whatsapp'));
    $waText = __('مرحبًا، أرغب في الاستفسار عن :unit', ['unit' => $title]).' — '.route('site.unit', $unit);
    $location = collect([$unit->district?->name, $unit->city?->name])->filter()->implode('، ');
    $facts = array_filter([
        ['building', __('النوع'), $unit->category ? __($unit->category->title) : null],
        ['bed', __('الغرف'), $unit->rooms ?: null],
        ['area', __('المساحة'), $unit->area ? number_format((float) $unit->area).' '.__('م²') : null],
        ['stairs', __('الدور'), $unit->floor ?: null],
        ['sofa', __('التأثيث'), $unit->is_furnished ? __('مفروشة') : null],
        ['tag', __('الدفع'), ! $sale && $unit->rent_payment_cycle ? $unit->rent_payment_cycle->getLabel() : null],
    ], fn ($f) => filled($f[2]));
@endphp

@section('title', $title.' | '.\App\Support\Website::name())
@section('description', str($unit->localized('details') ?: $location)->limit(150))
@if ($photos->isNotEmpty())
    @section('og_image', route('site.media', $photos->first()))
@endif

@push('head')
<style>
    .crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: .85rem; color: var(--muted); padding: 20px 0 10px; }
    .crumbs a:hover { color: var(--primary); }
    .u-head { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 12px 24px; margin-bottom: 18px; }
    .u-head h1 { margin: 6px 0 4px; font-size: clamp(1.5rem, 3vw, 2.2rem); line-height: 1.3; font-weight: 800; }
    .u-head .tags { display: flex; gap: 6px; }
    .u-head .tag { box-shadow: none; border: 1px solid var(--primary-line); background: var(--primary-soft); }
    .u-head .tag.is-sale { border-color: #fed7aa; background: var(--sale-soft); }
    .u-head .tag.is-featured { border-color: #f59e0b; background: #f59e0b; color: #fff; }
    .u-share { display: flex; gap: 8px; }
    .u-share .btn { padding: 9px 14px; }

    .gallery { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 8px; height: clamp(280px, 46vw, 520px); border-radius: 22px; overflow: hidden; }
    .gallery button { all: unset; display: block; width: 100%; height: 100%; min-height: 0; cursor: zoom-in; position: relative; overflow: hidden; background: var(--primary-soft); }
    .gallery button:first-child { grid-row: 1 / span 2; }
    .gallery img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
    .gallery button:hover img { transform: scale(1.04); }
    .gallery .more { position: absolute; inset: 0; display: grid; place-items: center; background: rgb(15 23 42 / .5); color: #fff; font-weight: 800; font-size: 1.1rem; }
    .gallery.n1 { grid-template-columns: 1fr; grid-template-rows: 1fr; } .gallery.n1 button:first-child { grid-row: auto; }
    .gallery.n2 { grid-template-columns: 1fr 1fr; grid-template-rows: 1fr; } .gallery.n2 button:first-child { grid-row: auto; }
    .gallery.n3 { grid-template-columns: 2fr 1fr; }
    .gallery.n4 { grid-template-columns: 2fr 1fr 1fr; } .gallery.n4 button:nth-child(4) { grid-column: span 2; }
    .gallery-empty { height: 320px; border-radius: 22px; display: grid; place-items: center; background: linear-gradient(135deg, var(--primary-soft), #e2e8f0); color: var(--primary-line); }
    .gallery-empty .ic { width: 90px; height: 90px; }

    .u-layout { display: grid; grid-template-columns: minmax(0, 1fr) 370px; gap: 28px; align-items: start; margin-top: 28px; }
    .u-block { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 24px; margin-bottom: 20px; }
    .u-block h2 { margin: 0 0 14px; font-size: 1.2rem; font-weight: 800; }
    .facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    .fact { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; background: var(--bg); }
    .fact .ic { width: 38px; height: 38px; padding: 9px; border-radius: 11px; background: #fff; color: var(--primary); border: 1px solid var(--line); }
    .fact small { display: block; color: var(--muted); font-size: .76rem; }
    .fact b { font-size: .98rem; }
    .details { white-space: pre-line; margin: 0; color: var(--ink-2); font-size: 1.02rem; }
    .map { width: 100%; height: 300px; border: 0; border-radius: 14px; background: var(--line); }
    .map-link { display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; color: var(--primary); font-weight: 700; }

    .aside { position: sticky; top: 92px; display: grid; gap: 16px; }
    .price-card { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 22px; box-shadow: var(--shadow); }
    .price-card .label { color: var(--muted); font-size: .85rem; font-weight: 700; }
    .price-card .amount { font-size: 2rem; font-weight: 800; line-height: 1.15; margin: 2px 0; }
    .price-card .amount small { font-size: .9rem; font-weight: 600; color: var(--muted); }
    .price-card .cta { display: grid; gap: 10px; margin-top: 18px; }
    .price-card .cta-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .office { display: flex; align-items: center; gap: 12px; padding: 16px 18px; background: #fff; border: 1px solid var(--line); border-radius: var(--radius); }
    .office img, .office .mono { width: 48px; height: 48px; border-radius: 12px; object-fit: contain; border: 1px solid var(--line); }
    .office .mono { display: grid; place-items: center; background: var(--primary); color: #fff; font-weight: 800; border: 0; }
    .office small { display: block; color: var(--muted); font-size: .8rem; }

    .lightbox { position: fixed; inset: 0; z-index: 60; background: rgb(2 6 23 / .92); display: none; align-items: center; justify-content: center; padding: 56px 16px; }
    .lightbox.is-open { display: flex; }
    .lightbox img { max-height: 100%; max-width: 100%; border-radius: 12px; object-fit: contain; }
    .lightbox button { position: absolute; background: rgb(255 255 255 / .12); color: #fff; border: 0; width: 48px; height: 48px; border-radius: 50%; font-size: 1.6rem; cursor: pointer; display: grid; place-items: center; }
    .lightbox button:hover { background: rgb(255 255 255 / .22); }
    .lightbox .close { top: 16px; inset-inline-end: 16px; }
    .lightbox .prev { inset-inline-start: 16px; top: 50%; transform: translateY(-50%); }
    .lightbox .next { inset-inline-end: 16px; top: 50%; transform: translateY(-50%); }
    .lightbox .count { position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%); color: #cbd5e1; font-weight: 700; font-size: .9rem; }

    .mbar { display: none; }
    @media (max-width: 960px) {
        .u-layout { grid-template-columns: 1fr; }
        .aside { position: static; }
    }
    @media (max-width: 640px) {
        .gallery { grid-template-columns: 1fr 1fr !important; grid-template-rows: 2fr 1fr !important; height: 360px; border-radius: 16px; }
        .gallery button:first-child { grid-column: 1 / -1; grid-row: auto !important; }
        .gallery button:nth-child(n+4) { display: none; }
        .gallery.n1 { grid-template-rows: 1fr !important; }
        .u-block { padding: 18px; }
        .price-card .cta { display: none; }
        .mbar { display: flex; position: fixed; inset-inline: 0; bottom: 0; z-index: 35; gap: 8px; align-items: center; padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); background: #fff; border-top: 1px solid var(--line); box-shadow: 0 -8px 24px rgb(15 23 42 / .08); }
        .mbar .p { flex: 1; min-width: 0; font-weight: 800; line-height: 1.2; }
        .mbar .p small { display: block; font-weight: 600; font-size: .72rem; color: var(--muted); }
        .mbar .btn { padding: 10px 14px; }
        footer.site { padding-bottom: 72px; }
    }
</style>
@endpush

@section('content')
<div class="container">
    <nav class="crumbs" aria-label="{{ __('مسار التنقل') }}">
        <a href="{{ route('site.home') }}">{{ __('الرئيسية') }}</a><span>/</span>
        <a href="{{ route('site.home', ['offer' => $unit->offer_type->value]) }}#units">{{ $sale ? __('للبيع') : __('للإيجار') }}</a><span>/</span>
        <span>{{ $title }}</span>
    </nav>

    <div class="u-head">
        <div>
            <div class="tags">
                <span @class(['tag', 'is-sale' => $sale])>{{ $sale ? __('للبيع') : __('للإيجار') }}</span>
                @if ($unit->is_featured)<span class="tag is-featured">@include('site.partials.icon', ['name' => 'star', 'stroke' => 2]){{ __('مميز') }}</span>@endif
            </div>
            <h1>{{ $title }}</h1>
            @if ($location || $unit->building)
                <div class="loc">@include('site.partials.icon', ['name' => 'pin']){{ collect([$unit->building?->title, $location])->filter()->implode(' — ') }}</div>
            @endif
        </div>
        <div class="u-share">
            <button type="button" class="btn btn-outline" data-share data-title="{{ $title }}" data-copied="{{ __('تم نسخ الرابط') }}">@include('site.partials.icon', ['name' => 'share']){{ __('مشاركة') }}</button>
        </div>
    </div>

    @if ($photos->isNotEmpty())
        @php($shown = $photos->take(5))
        <div class="gallery n{{ $shown->count() }}">
            @foreach ($shown as $i => $photo)
                <button type="button" data-open="{{ $i }}" aria-label="{{ __('عرض الصورة :n', ['n' => $i + 1]) }}">
                    <img src="{{ route('site.media', $i === 0 ? $photo : ['media' => $photo, 'size' => 'thumb']) }}" alt="{{ $i === 0 ? $title : '' }}" @if ($i > 0) loading="lazy" @endif>
                    @if ($loop->last && $photos->count() > 5)<span class="more">+{{ $photos->count() - 5 }} {{ __('صور') }}</span>@endif
                </button>
            @endforeach
        </div>
    @else
        <div class="gallery-empty">@include('site.partials.icon', ['name' => 'building', 'stroke' => 1.1])</div>
    @endif

    <div class="u-layout">
        <div>
            @if ($facts)
                <div class="u-block">
                    <h2>{{ __('المواصفات') }}</h2>
                    <div class="facts">
                        @foreach ($facts as [$icon, $label, $value])
                            <div class="fact">@include('site.partials.icon', ['name' => $icon])<div><small>{{ $label }}</small><b>{{ $value }}</b></div></div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($details = $unit->localized('details'))
                <div class="u-block">
                    <h2>{{ __('عن الوحدة') }}</h2>
                    <p class="details">{{ $details }}</p>
                </div>
            @endif

            @if ($map = \App\Support\Maps::embedUrl($unit->latitude, $unit->longitude))
                <div class="u-block">
                    <h2>{{ __('الموقع') }}</h2>
                    <iframe class="map" src="{{ $map }}" title="{{ __('الموقع') }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    <a class="map-link" href="{{ \App\Support\Maps::directionsUrl($unit->latitude, $unit->longitude) }}" target="_blank" rel="noopener">@include('site.partials.icon', ['name' => 'pin']){{ __('فتح في الخرائط') }} ↗</a>
                </div>
            @endif

            @if ($slots)
                <div class="u-block" id="viewing">
                    <h2>{{ __('احجز موعد معاينة') }}</h2>
                    @include('site.partials.viewing-form')
                </div>
            @endif

            <div class="u-block" id="inquiry">
                <h2>{{ $slots ? __('أو أرسل استفسارك') : __('اطلب معاينة أو استفسر') }}</h2>
                @include('site.partials.inquiry-form', ['action' => route('site.unit.inquiry', $unit), 'default' => __('أرغب في الاستفسار عن :unit', ['unit' => $title])])
            </div>
        </div>

        <aside class="aside">
            <div class="price-card">
                <div class="label">{{ $sale ? __('سعر البيع') : __('الإيجار') }}</div>
                @if ($price)
                    <div class="amount">{{ number_format($price['amount']) }} <small>{{ Settings::currencyLabel() }}@if ($price['per']) / {{ $price['per'] }}@endif</small></div>
                    @if (! $sale && $unit->rent_payment_cycle)<div class="label" style="font-weight:600">{{ __('الدفع') }}: {{ $unit->rent_payment_cycle->getLabel() }}</div>@endif
                @else
                    <div class="amount" style="font-size:1.3rem">{{ __('السعر عند التواصل') }}</div>
                @endif
                <div class="cta">
                    <a class="btn btn-primary btn-block" href="#{{ $slots ? 'viewing' : 'inquiry' }}">@include('site.partials.icon', ['name' => 'calendar', 'stroke' => 2]){{ $slots ? __('احجز موعد معاينة') : __('اطلب معاينة') }}</a>
                    <div class="cta-row">
                        @if ($whatsapp)<a class="btn btn-wa" href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener">@include('site.partials.whatsapp-icon', ['size' => 18]){{ __('واتساب') }}</a>@endif
                        @if ($phone)<a class="btn btn-outline" href="tel:{{ $phone }}" @if (! $whatsapp) style="grid-column:1/-1" @endif>@include('site.partials.icon', ['name' => 'phone']){{ __('اتصال') }}</a>@endif
                    </div>
                </div>
            </div>
            <div class="office">
                @if (Settings::get('logo'))<img src="{{ route('site.logo') }}" alt="">@else<span class="mono">{{ mb_substr(\App\Support\Website::name(), 0, 1) }}</span>@endif
                <div><small>{{ __('معروضة بواسطة') }}</small><b>{{ \App\Support\Website::name() }}</b></div>
            </div>
        </aside>
    </div>
</div>

@if ($similar->isNotEmpty())
<section>
    <div class="container">
        <div class="section-head"><div><p class="eyebrow">{{ __('قد يعجبك أيضًا') }}</p><h2 class="section-title">{{ __('وحدات مشابهة') }}</h2></div></div>
        <div class="grid">@foreach ($similar as $item) @include('site.partials.unit-card', ['unit' => $item]) @endforeach</div>
    </div>
</section>
@endif

<div class="mbar">
    <div class="p">
        @if ($price){{ number_format($price['amount']) }} {{ Settings::currencyLabel() }}<small>{{ $price['per'] ?? ($sale ? __('سعر البيع') : '') }}</small>@else{{ __('السعر عند التواصل') }}@endif
    </div>
    @if ($whatsapp)<a class="btn btn-wa" href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener" aria-label="{{ __('واتساب') }}">@include('site.partials.whatsapp-icon', ['size' => 18])</a>@endif
    @if ($phone)<a class="btn btn-outline" href="tel:{{ $phone }}" aria-label="{{ __('اتصال') }}">@include('site.partials.icon', ['name' => 'phone'])</a>@endif
    <a class="btn btn-primary" href="#{{ $slots ? 'viewing' : 'inquiry' }}">{{ $slots ? __('احجز معاينة') : __('استفسر') }}</a>
</div>

@if ($photos->isNotEmpty())
<div class="lightbox" role="dialog" aria-modal="true" aria-label="{{ __('صور الوحدة') }}">
    <img src="" alt="">
    <button type="button" class="close" aria-label="{{ __('إغلاق') }}">×</button>
    @if ($photos->count() > 1)
        <button type="button" class="prev" aria-label="{{ __('السابقة') }}">{{ $isRtl ? '›' : '‹' }}</button>
        <button type="button" class="next" aria-label="{{ __('التالية') }}">{{ $isRtl ? '‹' : '›' }}</button>
    @endif
    <span class="count"></span>
</div>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const photos = @json($photos->map(fn ($p) => route('site.media', $p))->values());
    const box = document.querySelector('.lightbox');
    if (box && photos.length) {
        const img = box.querySelector('img'), count = box.querySelector('.count');
        let at = 0;
        const show = (i) => { at = (i + photos.length) % photos.length; img.src = photos[at]; count.textContent = (at + 1) + ' / ' + photos.length; };
        const open = (i) => { show(i); box.classList.add('is-open'); document.body.style.overflow = 'hidden'; };
        const close = () => { box.classList.remove('is-open'); document.body.style.overflow = ''; };
        document.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => open(+b.dataset.open)));
        box.querySelector('.close').addEventListener('click', close);
        box.querySelector('.prev')?.addEventListener('click', () => show(at - 1));
        box.querySelector('.next')?.addEventListener('click', () => show(at + 1));
        box.addEventListener('click', (e) => { if (e.target === box) close(); });
        document.addEventListener('keydown', (e) => {
            if (!box.classList.contains('is-open')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') show(at + (document.dir === 'rtl' ? 1 : -1));
            if (e.key === 'ArrowRight') show(at + (document.dir === 'rtl' ? -1 : 1));
        });
    }
    document.querySelectorAll('[data-share]').forEach((b) => b.addEventListener('click', async () => {
        const data = { title: b.dataset.title, url: location.href.split('#')[0] };
        if (navigator.share) { try { await navigator.share(data); } catch (e) {} return; }
        try { await navigator.clipboard.writeText(data.url); b.lastChild.textContent = b.dataset.copied; } catch (e) {}
    }));
})();
</script>
@endpush
