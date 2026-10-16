@php
    $steps = \App\Filament\Admin\Widgets\GettingStarted::steps();
    $done = count(array_filter($steps, fn ($s) => $s['done']));
    $total = count($steps);
@endphp
<x-filament-widgets::widget>
    <div class="rowad-start">
        <div class="rowad-start__head">
            <div>
                <p class="rowad-start__kicker">{{ __('البداية') }}</p>
                <h3 class="rowad-start__title">{{ __('جهّز مؤسستك في :total خطوات', ['total' => $total]) }}</h3>
                <p class="rowad-start__sub">{{ __('أنجزت :done من :total — أكمل الباقي ليعمل النظام بكامل قوته.', ['done' => $done, 'total' => $total]) }}</p>
            </div>
            <div class="rowad-start__meter" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
                <span style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span>
            </div>
            <button type="button" class="rowad-start__dismiss" wire:click="dismiss" wire:confirm="{{ __('تُخفى القائمة نهائيًا. متابعة؟') }}">{{ __('إخفاء القائمة') }}</button>
        </div>
        <ol class="rowad-start__list">
            @foreach ($steps as $i => $step)
                <li class="rowad-start__step {{ $step['done'] ? 'is-done' : '' }}">
                    <span class="rowad-start__num">@if ($step['done'])<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>@else{{ $i + 1 }}@endif</span>
                    <span class="rowad-start__text"><b>{{ $step['label'] }}</b><small>{{ $step['hint'] }}</small></span>
                    @if (! $step['done'] && $step['url'])
                        <a class="rowad-start__go" href="{{ $step['url'] }}">{{ __('ابدأ') }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
</x-filament-widgets::widget>
