{{-- An owner payout run: who is paid how much, to which IBAN — the sheet that goes with the bank file. --}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
{!! $header !!}
<div class="doc-title">
    <div>
        <div class="doc-kicker">OWNER PAYOUT</div>
        <h1>{{ __('كشف توزيع مستحقات الملاك') }}</h1>
    </div>
    <div class="doc-ids">
        <div><span>{{ __('رقم الدفعة') }}</span><b>{{ $payout->number }}</b></div>
        <div><span>{{ __('التاريخ') }}</span><b>{{ $payout->issued_at->format('Y-m-d') }}</b></div>
        <div><span>{{ __('مستحقات حتى') }}</span><b>{{ $payout->dues_until->format('Y-m-d') }}</b></div>
    </div>
</div>
<p class="doc-lead">{{ __('الحالة') }}: <span class="badge">{{ $payout->status->getLabel() }}</span>@if ($payout->bankAccount) — {{ __('من حساب :a', ['a' => $payout->bankAccount->holder_name]) }}@endif</p>

<div class="section">
    <table class="data">
        <thead><tr><th>#</th><th>{{ __('المالك') }}</th><th>{{ __('المستفيد') }}</th><th>{{ __('الآيبان') }}</th><th style="text-align:end">{{ __('المستحقات') }}</th><th style="text-align:end">{{ __('الخصومات') }}</th><th style="text-align:end">{{ __('الصافي') }}</th></tr></thead>
        <tbody>
            @foreach ($payout->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->owner?->full_name }}</td>
                    <td>{{ $item->beneficiary }}@if ($item->bank_name)<br><span class="muted">{{ $item->bank_name }}</span>@endif</td>
                    <td class="num" style="text-align:start">{{ \App\Support\Iban::format($item->iban) }}</td>
                    <td class="num">{{ $money($item->dues) }}</td>
                    <td class="num">{{ $money($item->deductions) }}</td>
                    <td class="num"><b>{{ $money($item->amount) }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="section">
    <div class="totals">
        <div class="total-card"><span>{{ __('عدد الملاك') }}</span><b>{{ $payout->owners_count }}</b></div>
        <div class="total-card"><span>{{ __('الخصومات') }}</span><b>{{ $money($payout->items->sum('deductions')) }}</b></div>
        <div class="total-card accent"><span>{{ __('صافي التحويل') }} ({{ $currency }})</span><b>{{ $money($payout->total) }}</b></div>
    </div>
    <p class="amount-words">{{ \App\Support\AmountInWords::format((float) $payout->total) }}</p>
</div>
<div class="signatures three">
    <div class="sign"><b>{{ __('أعدّه') }}</b></div>
    <div class="sign"><b>{{ __('المحاسب') }}</b></div>
    <div class="sign"><b>{{ __('المدير العام') }}</b></div>
</div>
{!! $footer !!}
