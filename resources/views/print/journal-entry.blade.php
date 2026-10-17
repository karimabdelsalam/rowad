{{-- A journal entry voucher (قيد يومية): its lines, totals and the signatures that approve it. --}}
@php $money = fn ($v) => (float) $v ? number_format((float) $v, 2) : ''; @endphp
{!! $header !!}
<div class="doc-title">
    <div>
        <div class="doc-kicker">JOURNAL ENTRY</div>
        <h1>{{ __('قيد يومية') }}</h1>
    </div>
    <div class="doc-ids">
        <div><span>{{ __('رقم القيد') }}</span><b>{{ $entry->number }}</b></div>
        <div><span>{{ __('التاريخ') }}</span><b>{{ $entry->entry_date->format('Y-m-d') }}</b></div>
        <div><span>{{ __('المصدر') }}</span><b>{{ $source }}</b></div>
    </div>
</div>
<p class="doc-lead">{{ $entry->description }}</p>

<div class="section">
    <table class="data">
        <thead><tr><th>{{ __('رقم الحساب') }}</th><th>{{ __('الحساب') }}</th><th>{{ __('البيان') }}</th><th style="text-align:end">{{ __('مدين') }}</th><th style="text-align:end">{{ __('دائن') }}</th></tr></thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td class="num" style="text-align:start">{{ $line->account->code }}</td>
                    <td>{{ $line->account->displayName() }}@if ($line->owner) <span class="muted">— {{ $line->owner->full_name }}</span>@endif</td>
                    <td class="muted">{{ $line->memo }}</td>
                    <td class="num">{{ $money($line->debit) }}</td>
                    <td class="num">{{ $money($line->credit) }}</td>
                </tr>
            @endforeach
            <tr><td colspan="3"><b>{{ __('الإجمالي') }}</b></td><td class="num"><b>{{ number_format((float) $lines->sum('debit'), 2) }}</b></td><td class="num"><b>{{ number_format((float) $lines->sum('credit'), 2) }}</b></td></tr>
        </tbody>
    </table>
    <p class="amount-words">{{ \App\Support\AmountInWords::format((float) $entry->total) }}</p>
</div>

<div class="signatures three">
    <div class="sign"><b>{{ __('أعدّه') }}</b><span>{{ $entry->creator?->name }}</span></div>
    <div class="sign"><b>{{ __('المحاسب') }}</b></div>
    <div class="sign"><b>{{ __('المدير المالي') }}</b></div>
</div>
{!! $footer !!}
