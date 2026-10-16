@php
    $tone = ['low' => 'ok', 'medium' => 'warn', 'high' => 'bad', 'new' => 'muted'][$risk['level']];
@endphp
<div class="rowad-risk is-{{ $tone }}">
    <div class="rowad-risk__top">
        <div class="rowad-risk__gauge" style="--p: {{ $risk['level'] === 'new' ? 0 : $risk['score'] }}">
            <span>{{ $risk['level'] === 'new' ? '—' : $risk['score'] }}</span>
        </div>
        <div>
            <p class="rowad-risk__label">{{ __('احتمال التأخر في السداد') }}</p>
            <p class="rowad-risk__level">{{ $risk['label'] }}</p>
        </div>
    </div>
    <ul class="rowad-risk__reasons">
        @foreach ($risk['reasons'] as $reason)
            <li>{{ $reason }}</li>
        @endforeach
    </ul>
    @if ($risk['stats']['past_due'] > 0)
        <p class="rowad-risk__foot">{{ __('من :count دفعة مستحقة حتى اليوم · يُحسب من سجل السداد لدى المؤسسة فقط.', ['count' => $risk['stats']['past_due']]) }}</p>
    @endif
</div>
