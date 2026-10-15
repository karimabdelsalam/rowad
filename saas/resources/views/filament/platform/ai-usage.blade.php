<x-filament-widgets::widget>
    <x-filament::section :heading="__('استهلاك الذكاء الاصطناعي هذا الشهر')"
        :description="__(':messages سؤال · :in رمز إدخال · :out رمز إخراج', ['messages' => number_format($totalMessages), 'in' => number_format($totalInput), 'out' => number_format($totalOutput)])">
        <style>
            .ai-use { display: grid; gap: 12px; }
            .ai-use__row { display: grid; grid-template-columns: minmax(140px, 1.2fr) 3fr auto; gap: 14px; align-items: center; font-size: .9rem; }
            .ai-use__bar { height: 8px; border-radius: 999px; background: rgb(15 23 42 / .07); overflow: hidden; }
            .dark .ai-use__bar { background: rgb(255 255 255 / .1); }
            .ai-use__bar span { display: block; height: 100%; border-radius: 999px; background: #0f766e; }
            .ai-use__bar span.full { background: #d97706; }
            .ai-use__num { font-variant-numeric: tabular-nums; color: var(--rowad-muted); white-space: nowrap; }
        </style>
        @if ($rows === [])
            <p style="color: var(--rowad-muted); margin: 0">{{ __('لم تستخدم أي مؤسسة المساعد الذكي هذا الشهر بعد.') }}</p>
        @else
            <div class="ai-use">
                @foreach ($rows as $row)
                    @php($pct = $row['limit'] ? min(100, round($row['messages'] / $row['limit'] * 100)) : null)
                    <div class="ai-use__row">
                        <b>{{ $row['name'] }}</b>
                        <div class="ai-use__bar" title="{{ $pct === null ? __('غير محدود') : $pct.'%' }}">
                            <span @class(['full' => $pct !== null && $pct >= 80]) style="width: {{ $pct ?? 100 }}%; {{ $pct === null ? 'opacity:.3' : '' }}"></span>
                        </div>
                        <span class="ai-use__num">{{ number_format($row['messages']) }} / {{ $row['limit'] ? number_format($row['limit']) : '∞' }} · {{ __(':tokens رمز', ['tokens' => number_format($row['tokens'])]) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
