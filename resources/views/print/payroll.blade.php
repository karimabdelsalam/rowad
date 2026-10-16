{{-- The whole month's payroll on one sheet, for approval and filing. --}}
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
{!! $header !!}
<div class="doc-title">
    <div>
        <div class="doc-kicker">PAYROLL</div>
        <h1>{{ __('مسيّر رواتب') }} — {{ $run->periodLabel() }}</h1>
    </div>
    <div class="doc-ids">
        <div><span>{{ __('الحالة') }}</span><b>{{ $run->status->getLabel() }}</b></div>
        <div><span>{{ __('الموظفون') }}</span><b>{{ $run->items->count() }}</b></div>
    </div>
</div>

<div class="section">
    <table class="data"><thead><tr>
        <th>#</th><th>{{ __('الموظف') }}</th><th>{{ __('الأساسي') }}</th><th>{{ __('البدلات') }}</th><th>{{ __('إضافي ومكافآت') }}</th><th>{{ __('الإجمالي') }}</th>
        <th>{{ __('تأمينات') }}</th><th>{{ __('حسومات') }}</th><th>{{ __('سلف') }}</th><th>{{ __('أخرى') }}</th><th>{{ __('الصافي') }}</th>
    </tr></thead>
    <tbody>
        @foreach ($run->items as $i => $item)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ $item->employee?->full_name }}<br><small style="color:#64748b">{{ $item->employee?->job_title }}</small></td>
                <td class="num">{{ $money($item->basic) }}</td>
                <td class="num">{{ $money((float) $item->housing + (float) $item->transport + (float) $item->other_allowances) }}</td>
                <td class="num">{{ $money((float) $item->overtime + (float) $item->bonus) }}</td>
                <td class="num"><b>{{ $money($item->gross) }}</b></td>
                <td class="num">{{ $money($item->gosi_employee) }}</td>
                <td class="num">{{ $money($item->penalties) }}</td>
                <td class="num">{{ $money($item->advances) }}</td>
                <td class="num">{{ $money((float) $item->unpaid_leave + (float) $item->other_deductions) }}</td>
                <td class="num"><b>{{ $money($item->net) }}</b></td>
            </tr>
        @endforeach
    </tbody></table>
</div>

<div class="section">
    <div class="totals">
        <div><span>{{ __('إجمالي الاستحقاقات') }}</span><b>{{ $money($run->total_gross) }}</b></div>
        <div><span>{{ __('إجمالي الاستقطاعات') }}</span><b>{{ $money($run->total_deductions) }}</b></div>
        <div class="grand"><span>{{ __('صافي الرواتب') }}</span><b>{{ $money($run->total_net) }} {{ $currency }}</b></div>
    </div>
    <p class="doc-lead" style="margin-top:8px">{{ __('حصة المنشأة في التأمينات الاجتماعية') }}: {{ $money($run->total_gosi_employer) }} {{ $currency }}</p>
</div>

<div class="signatures three">
    <div class="sign"><b>{{ __('إعداد') }}</b>{{ $run->creator?->name }}</div>
    <div class="sign"><b>{{ __('اعتماد') }}</b>{{ $run->approver?->name }}</div>
    <div class="sign"><b>{{ __('المدير العام') }}</b></div>
</div>
{!! $footer !!}
