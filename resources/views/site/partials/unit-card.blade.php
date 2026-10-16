@php
    $photos = $unit->getMedia('photos');
    $cover = $photos->first();
    $sale = $unit->offer_type === \App\Enums\OfferType::Sale;
    $price = $unit->publicPrice();
@endphp
<a href="{{ route('site.unit', $unit) }}" class="card">
    <div class="card__photo">
        @if ($cover)
            <img src="{{ route('site.media', ['media' => $cover, 'size' => 'thumb']) }}" alt="{{ $unit->localized('title') }}" loading="lazy">
        @else
            <span class="ph">@include('site.partials.icon', ['name' => 'building', 'stroke' => 1.2])</span>
        @endif
        <div class="card__tags">
            <span @class(['tag', 'is-sale' => $sale])>{{ $sale ? __('للبيع') : __('للإيجار') }}</span>
            @if ($unit->is_featured)<span class="tag is-featured">@include('site.partials.icon', ['name' => 'star', 'stroke' => 2]){{ __('مميز') }}</span>@endif
        </div>
        @if ($photos->count() > 1)<span class="card__count">@include('site.partials.icon', ['name' => 'photo']){{ $photos->count() }}</span>@endif
    </div>
    <div class="card__body">
        <h3>{{ $unit->localized('title') }}</h3>
        @if ($unit->city || $unit->district)
            <div class="loc">@include('site.partials.icon', ['name' => 'pin']){{ collect([$unit->district?->name, $unit->city?->name])->filter()->implode('، ') }}</div>
        @endif
        <div class="feats">
            @if ($unit->category)<span>@include('site.partials.icon', ['name' => 'building']){{ __($unit->category->title) }}</span>@endif
            @if ($unit->rooms)<span>@include('site.partials.icon', ['name' => 'bed']){{ trans_choice('{1} غرفة|{2} غرفتان|[3,10] :count غرف|[11,*] :count غرفة', $unit->rooms) }}</span>@endif
            @if ($unit->area)<span>@include('site.partials.icon', ['name' => 'area'])<bdi>{{ number_format((float) $unit->area) }}</bdi> {{ __('م²') }}</span>@endif
            @if ($unit->is_furnished)<span>@include('site.partials.icon', ['name' => 'sofa']){{ __('مفروشة') }}</span>@endif
        </div>
        <div class="card__foot">
            @if ($price)
                <div class="price">{{ number_format($price['amount']) }} <small>{{ \App\Support\Settings::currencyLabel() }}@if ($price['per']) / {{ $price['per'] }}@endif</small></div>
            @else
                <div class="price is-ask">{{ __('السعر عند التواصل') }}</div>
            @endif
            <span class="card__more">{{ __('التفاصيل') }}@include('site.partials.icon', ['name' => 'arrow', 'stroke' => 2])</span>
        </div>
    </div>
</a>
