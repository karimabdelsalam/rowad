@if ($advice === null)
    <p class="rowad-advice__empty">{{ __('لا توجد عقود حديثة كافية لوحدات مماثلة لاقتراح سعر.') }}</p>
@else
    @php
        $gap = $advice['gap_percent'];
        $span = max(1, $advice['high'] - $advice['low']);
        $pos = $advice['current'] ? max(0, min(100, ($advice['current'] - $advice['low']) / $span * 100)) : null;
    @endphp
    <div class="rowad-advice">
        <p class="rowad-advice__kicker">{{ __('الإيجار السنوي المقترح') }}</p>
        <p class="rowad-advice__value">{{ number_format($advice['suggested']) }} <small>{{ __('ر.س') }}</small></p>
        <div class="rowad-advice__range">
            <div class="rowad-advice__bar">@if ($pos !== null)<i style="inset-inline-start: {{ $pos }}%" title="{{ __('الإيجار الحالي') }}"></i>@endif</div>
            <div class="rowad-advice__ends"><span>{{ number_format($advice['low']) }}</span><span>{{ number_format($advice['high']) }}</span></div>
        </div>
        @if ($gap !== null)
            <p @class(['rowad-advice__gap', 'is-under' => $gap < -5, 'is-over' => $gap > 10])>
                @if ($gap < -5)
                    {{ __('الإيجار الحالي أقل من السوق بنحو :p٪ — فرصة عند التجديد.', ['p' => abs(round($gap))]) }}
                @elseif ($gap > 10)
                    {{ __('الإيجار الحالي أعلى من المماثل بنحو :p٪ — قد يطيل مدة الشغور.', ['p' => round($gap)]) }}
                @else
                    {{ __('الإيجار الحالي في حدود السوق.') }}
                @endif
            </p>
        @endif
        <p class="rowad-advice__basis">
            {{ \App\Services\Insights\RentAdvisor::basisLabel($advice['basis']) }} · {{ trans_choice('{1} عقد واحد|{2} عقدان|[3,10] :count عقود|[11,*] :count عقدًا', $advice['sample'], ['count' => $advice['sample']]) }}
            @if ($advice['per_m2']) · {{ number_format($advice['per_m2']) }} {{ __('ر.س/م²') }}@endif
            @if ($advice['confidence'] === 'low') · <b>{{ __('ثقة منخفضة') }}</b>@endif
        </p>
    </div>
@endif
