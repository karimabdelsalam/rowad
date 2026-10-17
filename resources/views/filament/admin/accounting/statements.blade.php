<x-filament-panels::page>
    @include('filament.admin.accounting.styles')
    @component('filament.admin.accounting.period-bar', ['title' => $this->getTitle(), 'period' => $this->periodLabel()])
        <div class="rowad-acc-tabs" style="margin-bottom:4px">
            <button type="button" wire:click="$set('tab', 'income')" @class(['is-on' => $tab === 'income'])>{{ __('قائمة الدخل') }}</button>
            <button type="button" wire:click="$set('tab', 'balance')" @class(['is-on' => $tab === 'balance'])>{{ __('المركز المالي') }}</button>
        </div>
    @endcomponent
    @php $fmt = fn (float $v) => number_format($v, 2); @endphp
    @if ($tab === 'income')
        @php $s = $this->income(); @endphp
        <div class="rowad-acc-card">
            <h3><span>{{ __('قائمة الدخل') }} <small>{{ $this->periodLabel() }}</small></span></h3>
            <table class="rowad-acc-table">
                <tbody>
                    <tr class="is-group"><td colspan="2">{{ __('الإيرادات') }}</td></tr>
                    @forelse ($s['revenue'] as $row)
                        <tr><td style="padding-inline-start:28px">{{ $row['account']->displayName() }}</td><td class="num">{{ $fmt($row['amount']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="muted" style="padding-inline-start:28px">—</td></tr>
                    @endforelse
                    <tr class="is-total"><td>{{ __('إجمالي الإيرادات') }}</td><td class="num">{{ $fmt($s['total_revenue']) }}</td></tr>
                    <tr class="is-group"><td colspan="2">{{ __('المصروفات') }}</td></tr>
                    @forelse ($s['expenses'] as $row)
                        <tr><td style="padding-inline-start:28px">{{ $row['account']->displayName() }}</td><td class="num">{{ $fmt($row['amount']) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="muted" style="padding-inline-start:28px">—</td></tr>
                    @endforelse
                    <tr class="is-total"><td>{{ __('إجمالي المصروفات') }}</td><td class="num">{{ $fmt($s['total_expenses']) }}</td></tr>
                    <tr class="is-total is-grand"><td>{{ $s['net'] >= 0 ? __('صافي الربح') : __('صافي الخسارة') }}</td><td @class(['num', 'neg' => $s['net'] < 0])>{{ $fmt($s['net']) }}</td></tr>
                </tbody>
            </table>
        </div>
    @else
        @php $b = $this->balance(); @endphp
        <div class="rowad-acc-grid">
            <div class="rowad-acc-card">
                <h3><span>{{ __('الأصول') }} <small>{{ __('في :date', ['date' => $this->until]) }}</small></span></h3>
                <table class="rowad-acc-table">
                    <tbody>
                        @foreach ($b['assets'] as $row)
                            <tr><td>{{ $row['account']->displayName() }}</td><td @class(['num', 'neg' => $row['amount'] < 0])>{{ $fmt($row['amount']) }}</td></tr>
                        @endforeach
                        <tr class="is-total is-grand"><td>{{ __('إجمالي الأصول') }}</td><td class="num">{{ $fmt($b['total_assets']) }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="rowad-acc-card">
                <h3><span>{{ __('الخصوم وحقوق الملكية') }} <small>{{ __('في :date', ['date' => $this->until]) }}</small></span></h3>
                <table class="rowad-acc-table">
                    <tbody>
                        <tr class="is-group"><td colspan="2">{{ __('الخصوم') }}</td></tr>
                        @foreach ($b['liabilities'] as $row)
                            <tr><td style="padding-inline-start:28px">{{ $row['account']->displayName() }}</td><td @class(['num', 'neg' => $row['amount'] < 0])>{{ $fmt($row['amount']) }}</td></tr>
                        @endforeach
                        <tr class="is-total"><td>{{ __('إجمالي الخصوم') }}</td><td class="num">{{ $fmt($b['total_liabilities']) }}</td></tr>
                        <tr class="is-group"><td colspan="2">{{ __('حقوق الملكية') }}</td></tr>
                        @foreach ($b['equity'] as $row)
                            <tr><td style="padding-inline-start:28px">{{ $row['account']->displayName() }}</td><td @class(['num', 'neg' => $row['amount'] < 0])>{{ $fmt($row['amount']) }}</td></tr>
                        @endforeach
                        <tr><td style="padding-inline-start:28px">{{ __('صافي ربح الفترة غير المرحّل') }}</td><td @class(['num', 'neg' => $b['profit'] < 0])>{{ $fmt($b['profit']) }}</td></tr>
                        <tr class="is-total"><td>{{ __('إجمالي حقوق الملكية') }}</td><td class="num">{{ $fmt($b['total_equity']) }}</td></tr>
                        <tr class="is-total is-grand"><td>{{ __('إجمالي الخصوم وحقوق الملكية') }}</td><td class="num">{{ $fmt($b['total_liabilities'] + $b['total_equity']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
