@php
    $percent = \App\Services\Ejar\EjarSheet::readiness($getRecord());
    $missing = \App\Services\Ejar\EjarSheet::missing($getRecord());
@endphp
<div class="rowad-ejar-ready">
    <div class="rowad-ejar-ready__bar"><span style="width: {{ $percent }}%" @class(['is-full' => $percent === 100])></span></div>
    <div class="rowad-ejar-ready__label">{{ __('جاهزية البيانات :p%', ['p' => $percent]) }}</div>
    @if ($missing)
        <ul>
            @foreach (array_slice($missing, 0, 5) as $item)
                <li>{{ $item }}</li>
            @endforeach
            @if (count($missing) > 5)
                <li class="is-more">{{ __('و:n غيرها', ['n' => count($missing) - 5]) }}</li>
            @endif
        </ul>
    @endif
</div>
