@php
    $record = $getRecord();
    $d = $resolve($record);
    $sub = array_values(array_filter($d['sub'] ?? [], fn ($v) => filled($v)));
@endphp
<div class="rowad-record">
    <div class="rowad-record__main">
        <div class="rowad-record__icon">
            <x-filament::icon :icon="$icon" />
        </div>
        <div style="min-width:0">
            @if (filled($d['kicker'] ?? null))<p class="rowad-record__kicker">{{ $d['kicker'] }}</p>@endif
            <h2 class="rowad-record__title">{{ $d['title'] }}</h2>
            @if ($sub)<p class="rowad-record__sub">@foreach ($sub as $line)<span>{{ $line }}</span>@endforeach</p>@endif
        </div>
    </div>
    @if (! empty($d['facts']))
        <div class="rowad-record__facts">
            @foreach ($d['facts'] as $fact)
                <div class="rowad-record__fact {{ isset($fact['tone']) ? 'is-'.$fact['tone'] : '' }}"><span>{{ $fact['label'] }}</span><b>{{ $fact['value'] }}</b></div>
            @endforeach
        </div>
    @endif
</div>
