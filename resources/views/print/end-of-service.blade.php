{{-- End-of-service settlement sheet (Saudi Labour Law art. 84/85/87). --}}
@php $money = fn ($v) => number_format((float) $v, 2); $c = $s->calculation ?? []; @endphp
{!! $header !!}
<div class="doc-title">
    <div>
        <div class="doc-kicker">END OF SERVICE</div>
        <h1>{{ __('تسوية نهاية خدمة') }}</h1>
    </div>
    <div class="doc-ids">
        <div><span>{{ __('تاريخ الانتهاء') }}</span><b>{{ $s->termination_date?->format('Y-m-d') }}</b></div>
        <div><span>{{ __('الحالة') }}</span><b>{{ $s->status === 'paid' ? __('مصروفة') : __('مسودة') }}</b></div>
    </div>
</div>

<div class="section">
    <h2>{{ __('بيانات الموظف') }}</h2>
    <dl class="kv">
        <div class="wide"><dt>{{ __('الاسم') }}</dt><dd>{{ $employee->full_name }}</dd></div>
        <div><dt>{{ __('المسمى الوظيفي') }}</dt><dd>{{ $employee->job_title ?: '-' }}</dd></div>
        <div><dt>{{ __('رقم الهوية') }}</dt><dd>{{ $employee->id_number ?: '-' }}</dd></div>
        <div><dt>{{ __('تاريخ التعيين') }}</dt><dd>{{ $employee->start_date?->format('Y-m-d') ?: '-' }}</dd></div>
        <div><dt>{{ __('مدة الخدمة') }}</dt><dd>{{ $s->service_years }} {{ __('سنة') }}</dd></div>
        <div><dt>{{ __('سبب الانتهاء') }}</dt><dd>{{ $s->reason?->getLabel() }}</dd></div>
        <div><dt>{{ __('الأجر الأخير الشامل') }}</dt><dd>{{ $money($s->last_wage) }}</dd></div>
    </dl>
</div>

<div class="section">
    <h2>{{ __('حساب المكافأة (المادة 84)') }}</h2>
    <table class="data"><thead><tr><th>{{ __('البند') }}</th><th style="text-align:end">{{ __('المبلغ') }} ({{ $currency }})</th></tr></thead>
    <tbody>
        <tr><td>{{ __('الخمس سنوات الأولى — نصف أجر شهر عن كل سنة') }}</td><td class="num" style="text-align:end">{{ $money($c['first_five'] ?? 0) }}</td></tr>
        <tr><td>{{ __('ما زاد على خمس سنوات — أجر شهر عن كل سنة') }}</td><td class="num" style="text-align:end">{{ $money($c['after_five'] ?? 0) }}</td></tr>
        <tr><td>{{ __('المكافأة الكاملة') }}</td><td class="num" style="text-align:end">{{ $money($c['full_award'] ?? 0) }}</td></tr>
        <tr><td>{{ __('النسبة المستحقة حسب سبب الانتهاء (المادتان 85 و87)') }}</td><td class="num" style="text-align:end">{{ round((float) ($c['share'] ?? 0) * 100) }}%</td></tr>
        <tr><td><b>{{ __('المكافأة المستحقة') }}</b></td><td class="num" style="text-align:end"><b>{{ $money($s->award) }}</b></td></tr>
        <tr><td>{{ __('بدل رصيد الإجازات') }} ({{ (float) $s->leave_balance_days }} {{ __('يوم') }})</td><td class="num" style="text-align:end">{{ $money($s->leave_amount) }}</td></tr>
        <tr><td>{{ __('مستحقات أخرى') }}</td><td class="num" style="text-align:end">{{ $money($s->other_dues) }}</td></tr>
        <tr><td>{{ __('خصومات') }}</td><td class="num" style="text-align:end">- {{ $money($s->deductions) }}</td></tr>
    </tbody></table>
</div>

<div class="section">
    <div class="totals">
        <div><span>{{ __('المكافأة') }}</span><b>{{ $money($s->award) }}</b></div>
        <div><span>{{ __('الإجازات والمستحقات') }}</span><b>{{ $money((float) $s->leave_amount + (float) $s->other_dues) }}</b></div>
        <div class="grand"><span>{{ __('صافي التسوية') }}</span><b>{{ $money($s->net) }} {{ $currency }}</b></div>
    </div>
    <p class="doc-lead" style="margin-top:8px">{{ \App\Support\AmountInWords::format((float) $s->net) }}</p>
    @if ($s->paymentVoucher)<p class="doc-lead">{{ __('سند الصرف') }}: {{ $s->paymentVoucher->number }}</p>@endif
    @if (filled($s->notes))<p class="doc-lead">{{ $s->notes }}</p>@endif
</div>

<p class="doc-lead">{{ __('أقر أنا الموظف المذكور أعلاه بأنني استلمت كامل مستحقاتي عن فترة عملي، ولا يحق لي المطالبة بأي مبالغ أخرى.') }}</p>
<div class="signatures three">
    <div class="sign"><b>{{ __('الموظف') }}</b></div>
    <div class="sign"><b>{{ __('المحاسب') }}</b></div>
    <div class="sign"><b>{{ __('المدير العام') }}</b></div>
</div>
{!! $footer !!}
