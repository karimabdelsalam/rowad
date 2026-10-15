{{-- Legacy building/printable: one unit's data sheet, in the document design system (print/document.blade.php). --}}
@php
    $kv = fn (array $rows) => collect($rows)->filter(fn ($v) => filled($v));
    $contract = $unit->currentContract;
@endphp
{!! $header !!}
<div class="doc-title">
    <div>
        <div class="doc-kicker">PROPERTY SHEET</div>
        <h1>{{ __('بطاقة وحدة') }}</h1>
    </div>
    <div class="doc-ids">
        <div><span>{{ __('الوحدة') }}</span><b>{{ $unit->number ?: '#'.$unit->id }}</b></div>
        <div><span>{{ __('الحالة') }}</span><b>{{ $unit->status?->getLabel() }}</b></div>
    </div>
</div>

<div class="section">
    <h2>{{ __('بيانات الوحدة') }}</h2>
    <dl class="kv">
        <div class="wide"><dt>{{ __('العنوان') }}</dt><dd>{{ $unit->title }}</dd></div>
        <div class="wide"><dt>{{ __('العقار') }}</dt><dd>{{ $unit->building?->title }}</dd></div>
        @foreach ($kv([
            __('التصنيف') => $unit->category?->title, __('نوع العرض') => $unit->offer_type?->getLabel(),
            __('الدور') => $unit->floor, __('الغرف') => $unit->rooms, __('المساحة (م²)') => $unit->area ? (float) $unit->area : null,
            __('مفروشة') => $unit->is_furnished ? __('نعم') : __('لا'),
        ]) as $label => $value)
            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
        @if (filled($unit->details))<div class="full"><dt>{{ __('التفاصيل') }}</dt><dd style="font-weight:400">{{ $unit->details }}</dd></div>@endif
    </dl>
</div>

<div class="section">
    <h2>{{ __('الموقع') }}</h2>
    <dl class="kv">
        @foreach ($kv([__('المدينة') => $unit->city?->name, __('الحي') => $unit->district?->name, __('الشارع') => $unit->street, __('المنطقة') => $unit->zone]) as $label => $value)
            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
        @if (filled($unit->address))<div class="full"><dt>{{ __('العنوان التفصيلي') }}</dt><dd>{{ $unit->address }}</dd></div>@endif
    </dl>
</div>

<div class="section">
    <h2>{{ __('الملاك') }}</h2>
    <table class="data"><thead><tr><th>{{ __('الاسم') }}</th><th>{{ __('رقم الهوية') }}</th><th>{{ __('الجوال') }}</th><th>{{ __('نسبة الملكية') }}</th></tr></thead>
        <tbody>@foreach ($unit->owners as $o)<tr><td>{{ $o->full_name }}</td><td class="num">{{ $o->id_number }}</td><td class="num">{{ $o->mobile }}</td><td class="num">{{ (float) $o->pivot->share_percent }}%</td></tr>@endforeach</tbody></table>
</div>

<div class="section">
    <h2>{{ __('الصكوك') }}</h2>
    <table class="data"><thead><tr><th>{{ __('نوع الصك') }}</th><th>{{ __('رقم الصك') }}</th><th>{{ __('مصدر الصك') }}</th><th>{{ __('تاريخ الصك') }}</th></tr></thead>
        <tbody>@foreach ($unit->deeds as $d)<tr><td>{{ $d->deedType?->title }}</td><td class="num">{{ $d->number }}</td><td>{{ $d->source }}</td><td class="num">{{ $d->issue_date }}</td></tr>@endforeach</tbody></table>
</div>

<div class="section">
    <h2>{{ __('العدادات') }}</h2>
    <table class="data"><thead><tr><th>{{ __('النوع') }}</th><th>{{ __('رقم العداد') }}</th><th>{{ __('رقم الحساب') }}</th></tr></thead>
        <tbody>@foreach ($unit->meters as $m)<tr><td>{{ $m->meterType?->title }}</td><td class="num">{{ $m->number }}</td><td class="num">{{ $m->payment_account }}</td></tr>@endforeach</tbody></table>
</div>

<div class="section">
    <h2>{{ __('الأسعار والعقد الحالي') }}</h2>
    <div class="totals">
        <div class="total-card"><span>{{ __('الإيجار السنوي') }}</span><b>{{ number_format((float) $unit->rent_amount, 2) }} {{ $currency }}</b></div>
        <div class="total-card"><span>{{ __('دورة السداد') }}</span><b>{{ $unit->rent_payment_cycle?->getLabel() ?? '—' }}</b></div>
        <div class="total-card accent"><span>{{ __('العقد الحالي') }}</span><b>{{ $contract?->number ?? __('لا يوجد') }}</b></div>
    </div>
    @if ($contract instanceof \App\Models\RentContract)
        <p class="muted">{{ __('المستأجر') }}: <b>{{ $contract->clients->pluck('full_name')->implode('، ') }}</b> — {{ $contract->start_date?->format('Y-m-d') }} ← {{ $contract->end_date?->format('Y-m-d') }}</p>
    @endif
</div>
{!! $footer !!}
