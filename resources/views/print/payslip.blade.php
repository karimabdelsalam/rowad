{{-- One employee's salary slip for a payroll month (print/document.blade.php design system). --}}
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $earnings = collect([
        __('الراتب الأساسي') => $item->basic, __('بدل السكن') => $item->housing, __('بدل النقل') => $item->transport,
        __('بدلات أخرى') => $item->other_allowances, __('ساعات إضافية') => $item->overtime, __('مكافأة') => $item->bonus,
    ])->filter(fn ($v) => (float) $v > 0);
    $deductions = collect([
        __('التأمينات الاجتماعية (حصة الموظف)') => $item->gosi_employee, __('حسومات وجزاءات') => $item->penalties, __('قسط سلفة') => $item->advances,
        __('إجازة بلا راتب') . ($item->unpaid_leave_days ? ' ('.$item->unpaid_leave_days.' '.__('يوم').')' : '') => $item->unpaid_leave, __('خصومات أخرى') => $item->other_deductions,
    ])->filter(fn ($v) => (float) $v > 0);
@endphp
{!! $header !!}
<div class="doc-title">
    <div>
        <div class="doc-kicker">PAYSLIP</div>
        <h1>{{ __('قسيمة راتب') }} — {{ $run->periodLabel() }}</h1>
    </div>
    <div class="doc-ids">
        <div><span>{{ __('الموظف') }}</span><b>#{{ $employee->id }}</b></div>
        <div><span>{{ __('الحالة') }}</span><b>{{ $run->status->getLabel() }}</b></div>
    </div>
</div>

<div class="section">
    <h2>{{ __('بيانات الموظف') }}</h2>
    <dl class="kv">
        <div class="wide"><dt>{{ __('الاسم') }}</dt><dd>{{ $employee->full_name }}</dd></div>
        <div><dt>{{ __('المسمى الوظيفي') }}</dt><dd>{{ $employee->job_title ?: '-' }}</dd></div>
        <div><dt>{{ __('القسم') }}</dt><dd>{{ $employee->department ?: '-' }}</dd></div>
        <div><dt>{{ __('رقم الهوية') }}</dt><dd>{{ $employee->id_number ?: '-' }}</dd></div>
        <div><dt>{{ __('رقم التأمينات') }}</dt><dd>{{ $employee->gosi_number ?: '-' }}</dd></div>
        <div><dt>{{ __('البنك') }}</dt><dd>{{ $employee->bank?->title ?: '-' }}</dd></div>
        <div><dt>{{ __('الآيبان') }}</dt><dd style="direction:ltr">{{ $employee->iban ?: '-' }}</dd></div>
    </dl>
</div>

<div class="section">
    <h2>{{ __('الاستحقاقات') }}</h2>
    <table class="data"><thead><tr><th>{{ __('البند') }}</th><th style="text-align:end">{{ __('المبلغ') }} ({{ $currency }})</th></tr></thead>
        <tbody>@foreach ($earnings as $label => $value)<tr><td>{{ $label }}</td><td class="num" style="text-align:end">{{ $money($value) }}</td></tr>@endforeach
        <tr><td><b>{{ __('إجمالي الاستحقاقات') }}</b></td><td class="num" style="text-align:end"><b>{{ $money($item->gross) }}</b></td></tr></tbody></table>
</div>

<div class="section">
    <h2>{{ __('الاستقطاعات') }}</h2>
    <table class="data"><thead><tr><th>{{ __('البند') }}</th><th style="text-align:end">{{ __('المبلغ') }} ({{ $currency }})</th></tr></thead>
        <tbody>@forelse ($deductions as $label => $value)<tr><td>{{ $label }}</td><td class="num" style="text-align:end">{{ $money($value) }}</td></tr>@empty<tr><td colspan="2">{{ __('لا توجد استقطاعات') }}</td></tr>@endforelse
        <tr><td><b>{{ __('إجمالي الاستقطاعات') }}</b></td><td class="num" style="text-align:end"><b>{{ $money($item->deductionsTotal()) }}</b></td></tr></tbody></table>
</div>

<div class="section">
    <div class="totals">
        <div><span>{{ __('الإجمالي') }}</span><b>{{ $money($item->gross) }}</b></div>
        <div><span>{{ __('الاستقطاعات') }}</span><b>{{ $money($item->deductionsTotal()) }}</b></div>
        <div class="grand"><span>{{ __('صافي الراتب') }}</span><b>{{ $money($item->net) }} {{ $currency }}</b></div>
    </div>
    <p class="doc-lead" style="margin-top:8px">{{ \App\Support\AmountInWords::format((float) $item->net) }}</p>
    @if ($item->paymentVoucher)<p class="doc-lead">{{ __('سند الصرف') }}: {{ $item->paymentVoucher->number }} — {{ $item->paymentVoucher->issued_at?->format('Y-m-d') }}</p>@endif
    @if (filled($item->notes))<p class="doc-lead">{{ $item->notes }}</p>@endif
</div>

<div class="signatures">
    <div class="sign"><b>{{ __('المحاسب') }}</b></div>
    <div></div>
    <div class="sign"><b>{{ __('توقيع الموظف بالاستلام') }}</b></div>
</div>
{!! $footer !!}
