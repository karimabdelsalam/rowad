{{-- The lines of a journal entry, with totals. --}}
@include('filament.admin.accounting.styles')
@php
    /** @var \App\Models\JournalEntry $entry */
    $entry = $getRecord();
    $lines = $entry->lines()->with(['account', 'owner'])->orderByDesc('debit')->orderBy('id')->get();
    $canLedger = \App\Filament\Admin\Pages\Accounting\AccountLedger::canAccess();
@endphp
<div class="rowad-acc-table-wrap">
    <table class="rowad-acc-table">
        <thead>
            <tr><th>{{ __('رقم الحساب') }}</th><th>{{ __('الحساب') }}</th><th>{{ __('البيان') }}</th><th class="num">{{ __('مدين') }}</th><th class="num">{{ __('دائن') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td class="code">{{ $line->account->code }}</td>
                    <td>
                        @if ($canLedger)
                            <a href="{{ \App\Filament\Admin\Pages\Accounting\AccountLedger::getUrl(['account' => $line->account_id]) }}">{{ $line->account->displayName() }}</a>
                        @else
                            {{ $line->account->displayName() }}
                        @endif
                        @if ($line->owner) <small>· {{ $line->owner->full_name }}</small> @endif
                    </td>
                    <td class="muted">{{ $line->memo }}</td>
                    <td class="num">{{ (float) $line->debit ? number_format((float) $line->debit, 2) : '' }}</td>
                    <td class="num">{{ (float) $line->credit ? number_format((float) $line->credit, 2) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="3">{{ __('الإجمالي') }}</td><td class="num">{{ number_format((float) $lines->sum('debit'), 2) }}</td><td class="num">{{ number_format((float) $lines->sum('credit'), 2) }}</td></tr>
        </tfoot>
    </table>
</div>
