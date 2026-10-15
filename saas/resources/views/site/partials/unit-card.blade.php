@php($photo = $unit->coverPhotoUrl())
<a href="{{ route('site.unit', $unit) }}" class="card">
    <div class="photo" @if ($photo) style="background-image:url('{{ $photo }}')" @endif>
        @if ($unit->is_featured)<span class="badge">{{ __('مميز') }}</span>@endif
    </div>
    <div class="body">
        <h3>{{ $unit->localized('title') }}</h3>
        <div class="meta">
            @if ($unit->category)<span>{{ __($unit->category->title) }}</span>@endif
            @if ($unit->city)<span>{{ $unit->city->name }}@if ($unit->district) - {{ $unit->district->name }}@endif</span>@endif
        </div>
        <div class="meta">
            @if ($unit->rooms)<span>{{ $unit->rooms }} {{ __('غرف') }}</span>@endif
            @if ($unit->area)<span>{{ (float) $unit->area }} {{ __('م²') }}</span>@endif
            @if ($unit->is_furnished)<span>{{ __('مفروشة') }}</span>@endif
        </div>
        @if ($unit->show_price && $unit->rent_amount)
            @php($per = ['year' => __('سنويًا'), 'month' => __('شهريًا'), 'day' => __('يوميًا')][$unit->rent_period?->value] ?? null)
            <div class="price">
                {{ number_format((float) $unit->rent_amount) }} {{ __(\App\Support\Settings::get('currency', 'SAR')) }}
                @if ($per)<small>/ {{ $per }}</small>@endif
                @if ($unit->rent_payment_cycle)<div class="cycle">{{ __('الدفع') }}: {{ $unit->rent_payment_cycle->getLabel() }}</div>@endif
            </div>
        @else
            <div class="price">{{ __('السعر عند التواصل') }}</div>
        @endif
    </div>
</a>
