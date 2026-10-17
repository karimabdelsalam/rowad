<x-filament-panels::page>
    @include('filament.admin.accounting.styles')
    @php $rows = $this->rows(); @endphp
    @php $t = $this->totals(); @endphp
    @component('filament.admin.accounting.period-bar', ['title' => $this->getTitle(), 'period' => $this->periodLabel()])
        <label class="check"><input type="checkbox" wire:model.live="details"> {{ __('إظهار الحسابات الفرعية') }}</label>
    @endcomponent

    <div class="rowad-acc-card">
        <h3>
            <span>{{ __('ميزان المراجعة') }} <small>{{ $this->periodLabel() }}</small></span>
            @if (abs($t['debit'] - $t['credit']) < 0.005 && abs($t['closing_debit'] - $t['closing_credit']) < 0.005)
                <span class="rowad-acc-ok">✓ {{ __('الميزان متوازن') }}</span>
            @else
                <span class="rowad-acc-warn">{{ __('الميزان غير متوازن') }}</span>
            @endif
        </h3>
        @if ($rows->isEmpty())
            <p class="rowad-acc-empty">{{ __('لا توجد قيود في هذه الفترة.') }}</p>
        @else
            <div class="rowad-acc-table-wrap">
                <table class="rowad-acc-table">
                    <thead>
                        <tr>
                            <th rowspan="2">{{ __('رقم الحساب') }}</th><th rowspan="2">{{ __('اسم الحساب') }}</th>
                            <th class="num" colspan="2" style="text-align:center">{{ __('الرصيد الافتتاحي') }}</th>
                            <th class="num" colspan="2" style="text-align:center">{{ __('حركة الفترة') }}</th>
                            <th class="num" colspan="2" style="text-align:center">{{ __('الرصيد الختامي') }}</th>
                        </tr>
                        <tr>
                            <th class="num">{{ __('مدين') }}</th><th class="num">{{ __('دائن') }}</th>
                            <th class="num">{{ __('مدين') }}</th><th class="num">{{ __('دائن') }}</th>
                            <th class="num">{{ __('مدين') }}</th><th class="num">{{ __('دائن') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php
                                $a = $r['account'];
                                $debitNormal = $a->type->isDebitNormal();
                                $split = fn (float $v) => $debitNormal ? [max($v, 0), max(-$v, 0)] : [max(-$v, 0), max($v, 0)];
                                [$od, $oc] = $split($r['opening']);
                                [$cd, $cc] = $split($r['closing']);
                                $fmt = fn (float $v) => $v ? number_format($v, 2) : '';
                            @endphp
                            <tr @class(['is-group' => $a->is_group, 'is-top' => $r['depth'] === 0])>
                                <td class="code">{{ $a->code }}</td>
                                <td style="padding-inline-start: {{ 12 + $r['depth'] * 16 }}px">
                                    @if (! $a->is_group && \App\Filament\Admin\Pages\Accounting\AccountLedger::canAccess())
                                        <a href="{{ \App\Filament\Admin\Pages\Accounting\AccountLedger::getUrl(['account' => $a->id, 'from' => $this->from, 'until' => $this->until]) }}">{{ $a->displayName() }}</a>
                                    @else
                                        {{ $a->displayName() }}
                                    @endif
                                </td>
                                <td class="num">{{ $fmt($od) }}</td><td class="num">{{ $fmt($oc) }}</td>
                                <td class="num">{{ $fmt($r['debit']) }}</td><td class="num">{{ $fmt($r['credit']) }}</td>
                                <td class="num">{{ $fmt($cd) }}</td><td class="num">{{ $fmt($cc) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">{{ __('الإجمالي') }}</td>
                            <td class="num">{{ number_format($t['opening_debit'], 2) }}</td><td class="num">{{ number_format($t['opening_credit'], 2) }}</td>
                            <td class="num">{{ number_format($t['debit'], 2) }}</td><td class="num">{{ number_format($t['credit'], 2) }}</td>
                            <td class="num">{{ number_format($t['closing_debit'], 2) }}</td><td class="num">{{ number_format($t['closing_credit'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
