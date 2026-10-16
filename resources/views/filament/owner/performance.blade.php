@php
    $t = $report['totals'];
    $money = fn ($v) => number_format((float) $v, 2);
@endphp
<x-filament-panels::page>
    <div class="rowad-perf">
        <div class="rowad-perf__bar">
            <label for="perf-year">{{ __('السنة') }}</label>
            <select id="perf-year" wire:model.live="year">
                @foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
            </select>
        </div>

        <div class="rowad-perf__kpis">
            <div class="rowad-perf__kpi"><span>{{ __('حصتك من التحصيل') }}</span><b>{{ $money($t['gross']) }}</b><small>{{ __('ر.س') }}</small></div>
            <div class="rowad-perf__kpi"><span>{{ __('العمولات والمصروفات') }}</span><b>{{ $money($t['deductions']) }}</b><small>{{ __('ر.س') }}</small></div>
            <div class="rowad-perf__kpi is-main"><span>{{ __('صافي الدخل') }}</span><b>{{ $money($t['net']) }}</b><small>{{ __('ر.س') }}</small></div>
            <div class="rowad-perf__kpi"><span>{{ __('متوسط الإشغال') }}</span><b>{{ $t['occupancy'] }}%</b><small>{{ trans_choice('{1} عقار واحد|[2,*] :count عقارات', $t['units'], ['count' => $t['units']]) }}</small></div>
            <div class="rowad-perf__kpi {{ $t['arrears'] > 0 ? 'is-bad' : '' }}"><span>{{ __('متأخرات على المستأجرين') }}</span><b>{{ $money($t['arrears']) }}</b><small>{{ __('حصتك') }}</small></div>
            <div class="rowad-perf__kpi"><span>{{ __('العائد السنوي') }}</span><b>{{ $t['yield'] !== null ? $t['yield'].'%' : '—' }}</b><small>{{ $t['yield'] !== null ? __('على القيمة السوقية') : __('يظهر عند تسجيل القيمة') }}</small></div>
        </div>

        @if ($report['units'] === [])
            <div class="rowad-perf__empty">{{ __('لا توجد عقارات مسجلة باسمك بعد.') }}</div>
        @else
            <div class="rowad-perf__grid">
                @foreach ($report['units'] as $row)
                    <article class="rowad-perf__unit">
                        <header>
                            <h3>{{ $row['unit']->title }}</h3>
                            @if ($row['share'] < 100)<span class="rowad-perf__share">{{ __('حصتك :p%', ['p' => rtrim(rtrim(number_format($row['share'], 2), '0'), '.')]) }}</span>@endif
                        </header>
                        <dl>
                            <div><dt>{{ __('صافي الدخل') }}</dt><dd class="is-strong">{{ $money($row['net']) }}</dd></div>
                            <div><dt>{{ __('حصتك من التحصيل') }}</dt><dd>{{ $money($row['gross']) }}</dd></div>
                            <div><dt>{{ __('العمولات والمصروفات') }}</dt><dd>{{ $money($row['deductions']) }}</dd></div>
                            <div><dt>{{ __('متأخرات') }}</dt><dd class="{{ $row['arrears'] > 0 ? 'is-bad' : '' }}">{{ $money($row['arrears']) }}</dd></div>
                        </dl>
                        <div class="rowad-perf__occ">
                            <div class="rowad-perf__occbar"><i style="width: {{ min(100, $row['occupancy']) }}%"></i></div>
                            <span>{{ __('إشغال :p% · :days يوم شاغر', ['p' => $row['occupancy'], 'days' => $row['vacant_days']]) }}</span>
                        </div>
                        <footer>
                            @if ($row['yield'] !== null)
                                <span class="rowad-perf__chip">{{ __('العائد :p%', ['p' => $row['yield']]) }}</span>
                            @endif
                            @if ($row['market_gap'] !== null && $row['market_gap'] < -5)
                                <span class="rowad-perf__chip is-warn">{{ __('الإيجار أقل من السوق :p%', ['p' => abs(round($row['market_gap']))]) }}</span>
                            @elseif ($row['market_gap'] !== null && $row['market_gap'] > 10)
                                <span class="rowad-perf__chip is-warn">{{ __('الإيجار أعلى من المماثل :p%', ['p' => round($row['market_gap'])]) }}</span>
                            @endif
                        </footer>
                    </article>
                @endforeach
            </div>
            <p class="rowad-perf__note">{{ __('من بداية :year حتى اليوم. الأرقام من كشف حسابك لدى المكتب؛ العائد = صافي الدخل السنوي ÷ القيمة السوقية التقديرية.', ['year' => $report['year']]) }}</p>
        @endif
    </div>
</x-filament-panels::page>
