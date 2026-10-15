@extends('site.layout')

@section('title', $unit->localized('title').' | '.\App\Support\Website::name())
@section('description', str($unit->localized('details'))->limit(150))

@section('content')
@php($photos = $unit->getMedia('photos'))
<section>
    <div class="container" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:28px;align-items:start">
        <div>
            @if ($photos->isNotEmpty())
                <img src="{{ route('site.media', $photos->first()) }}" alt="{{ $unit->localized('title') }}" style="border-radius:var(--radius);width:100%;aspect-ratio:4/3;object-fit:cover">
                @if ($photos->count() > 1)
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:8px">
                        @foreach ($photos->skip(1)->take(8) as $photo)
                            <a href="{{ route('site.media', $photo) }}" target="_blank"><img src="{{ route('site.media', ['media' => $photo, 'size' => 'thumb']) }}" alt="" loading="lazy" style="border-radius:8px;aspect-ratio:1;object-fit:cover"></a>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="card"><div class="photo"></div></div>
            @endif
        </div>
        <div class="card"><div class="body" style="gap:12px">
            <h1 style="margin:0;font-size:1.6rem">{{ $unit->localized('title') }}</h1>
            <div class="meta">
                @if ($unit->category)<span>{{ __($unit->category->title) }}</span>@endif
                @if ($unit->city)<span>{{ $unit->city->name }}@if ($unit->district) - {{ $unit->district->name }}@endif</span>@endif
                @if ($unit->building)<span>{{ $unit->building->title }}</span>@endif
            </div>
            @if ($unit->show_price && $unit->rent_amount)
                @php($per = ['year' => __('سنويًا'), 'month' => __('شهريًا'), 'day' => __('يوميًا')][$unit->rent_period?->value] ?? null)
                <div class="price">{{ number_format((float) $unit->rent_amount) }} {{ __(\App\Support\Settings::get('currency', 'SAR')) }}
                    @if ($per)<small>/ {{ $per }}</small>@endif
                    @if ($unit->rent_payment_cycle)<div class="cycle">{{ __('الدفع') }}: {{ $unit->rent_payment_cycle->getLabel() }}</div>@endif</div>
            @endif
            <table style="width:100%;border-collapse:collapse">
                @foreach ([
                    __('عدد الغرف') => $unit->rooms,
                    __('المساحة (م²)') => $unit->area ? (float) $unit->area : null,
                    __('الدور') => $unit->floor,
                    __('مفروشة') => $unit->is_furnished ? __('نعم') : null,
                ] as $label => $value)
                    @if (filled($value))
                        <tr><td style="padding:6px 0;color:var(--muted)">{{ $label }}</td><td style="padding:6px 0;font-weight:700">{{ $value }}</td></tr>
                    @endif
                @endforeach
            </table>
            @if ($details = $unit->localized('details'))<p style="white-space:pre-line;margin:0">{{ $details }}</p>@endif
            <h2 style="margin:8px 0 0;font-size:1.2rem">{{ __('اطلب معاينة أو استفسر') }}</h2>
            @include('site.partials.inquiry-form', ['action' => route('site.unit.inquiry', $unit), 'default' => __('أرغب في الاستفسار عن :unit', ['unit' => $unit->localized('title')])])
        </div></div>
    </div>
</section>

@if ($similar->isNotEmpty())
<section>
    <div class="container">
        <h2 class="section-title">{{ __('وحدات مشابهة') }}</h2>
        <div class="grid">@foreach ($similar as $item) @include('site.partials.unit-card', ['unit' => $item]) @endforeach</div>
    </div>
</section>
@endif
@endsection
