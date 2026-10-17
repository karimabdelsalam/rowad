<x-filament-panels::page>
    @include('filament.admin.accounting.styles')
    @php $ledger = $this->ledger(); @endphp
    @php $account = $this->selected(); @endphp
    @component('filament.admin.accounting.period-bar', ['title' => $this->getTitle(), 'period' => $this->periodLabel()])
        <label>{{ __('الحساب') }}
            <select wire:model.live="account">
                @foreach ($this->accountOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    @endcomponent

    @if ($ledger)
        <div class="rowad-acc-card">
            <h3><span>{{ $account->label() }} <small>{{ $account->type->getLabel() }} · {{ $this->periodLabel() }}</small></span></h3>
            <div class="rowad-acc-table-wrap">
                <table class="rowad-acc-table">
                    <thead>
                        <tr><th>{{ __('التاريخ') }}</th><th>{{ __('رقم القيد') }}</th><th>{{ __('البيان') }}</th><th class="num">{{ __('مدين') }}</th><th class="num">{{ __('دائن') }}</th><th class="num">{{ __('الرصيد') }}</th></tr>
                    </thead>
                    <tbody>
                        <tr class="is-total"><td colspan="5">{{ __('رصيد أول المدة') }}</td><td class="num">{{ number_format($ledger['opening'], 2) }}</td></tr>
                        @forelse ($ledger['lines'] as $line)
                            <tr>
                                <td class="num" style="text-align:start">{{ \Illuminate\Support\Carbon::parse($line->entry_date)->format('Y-m-d') }}</td>
                                <td class="code">
                                    @if ($url = $this->entryUrl($line->entry_id)) <a href="{{ $url }}">{{ $line->number }}</a> @else {{ $line->number }} @endif
                                </td>
                                <td>{{ $line->description }} @if ($line->memo) <small>· {{ $line->memo }}</small> @endif</td>
                                <td class="num">{{ (float) $line->debit ? number_format((float) $line->debit, 2) : '' }}</td>
                                <td class="num">{{ (float) $line->credit ? number_format((float) $line->credit, 2) : '' }}</td>
                                <td @class(['num', 'neg' => $line->balance < 0])>{{ number_format($line->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="rowad-acc-empty">{{ __('لا توجد حركات في هذه الفترة.') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3">{{ __('إجمالي الفترة ورصيد آخر المدة') }}</td><td class="num">{{ number_format($ledger['debit'], 2) }}</td><td class="num">{{ number_format($ledger['credit'], 2) }}</td><td class="num">{{ number_format($ledger['closing'], 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
