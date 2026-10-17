{{-- A maintenance work order: what to fix, where, for whom, and the technician's sign-off. --}}
@php $money = fn ($v) => $v === null ? '-' : number_format((float) $v, 2).' '.$currency; @endphp
{!! $header !!}
<div class="doc-title">
    <div>
        <div class="doc-kicker">WORK ORDER</div>
        <h1>{{ __('أمر صيانة') }}</h1>
    </div>
    <div class="doc-ids">
        <div><span>{{ __('رقم الطلب') }}</span><b>{{ $request->code }}</b></div>
        <div><span>{{ __('التاريخ') }}</span><b>{{ $request->created_at?->format('Y-m-d') }}</b></div>
        <div><span>{{ __('الحالة') }}</span><b>{{ $request->status?->getLabel() }}</b></div>
    </div>
</div>

<div class="section">
    <h2>{{ __('الطلب') }}</h2>
    <dl class="kv">
        <div class="full"><dt>{{ __('العنوان') }}</dt><dd>{{ $request->title }}</dd></div>
        <div><dt>{{ __('التصنيف') }}</dt><dd>{{ $request->category?->getLabel() }}</dd></div>
        <div><dt>{{ __('الأولوية') }}</dt><dd>{{ $request->priority?->getLabel() }}</dd></div>
        <div class="wide"><dt>{{ __('الوقت المناسب') }}</dt><dd>{{ $request->preferred_time ?: '-' }}</dd></div>
        <div class="full"><dt>{{ __('الوصف') }}</dt><dd>{{ $request->description ?: '-' }}</dd></div>
    </dl>
</div>
<div class="section">
    <h2>{{ __('الموقع والمستأجر') }}</h2>
    <dl class="kv">
        <div class="wide"><dt>{{ __('الوحدة') }}</dt><dd>{{ $request->unit?->title }}@if ($request->unit?->building) — {{ $request->unit->building->title }}@endif</dd></div>
        <div class="wide"><dt>{{ __('العنوان') }}</dt><dd>{{ $request->unit?->address ?: ($request->unit?->building?->address ?: '-') }}</dd></div>
        <div class="wide"><dt>{{ __('المستأجر') }}</dt><dd>{{ $request->client?->displayName() ?: '-' }}</dd></div>
        <div class="wide"><dt>{{ __('الجوال') }}</dt><dd class="num" style="text-align:start">{{ $request->client?->mobile ?: '-' }}</dd></div>
    </dl>
</div>
<div class="section">
    <h2>{{ __('التنفيذ') }}</h2>
    <dl class="kv">
        <div class="wide"><dt>{{ __('الفني') }}</dt><dd>{{ $request->technician_name ?: '-' }}</dd></div>
        <div class="wide"><dt>{{ __('جوال الفني') }}</dt><dd class="num" style="text-align:start">{{ $request->technician_phone ?: '-' }}</dd></div>
        <div class="wide"><dt>{{ __('موعد الزيارة') }}</dt><dd>{{ $request->scheduled_at?->format('Y-m-d H:i') ?: '-' }}</dd></div>
        <div><dt>{{ __('التكلفة التقديرية') }}</dt><dd>{{ $money($request->estimated_cost) }}</dd></div>
        <div><dt>{{ __('التكلفة الفعلية') }}</dt><dd>{{ $money($request->cost) }}</dd></div>
        <div class="full"><dt>{{ __('ما تم عمله') }}</dt><dd style="min-height:3em">{{ $request->resolution }}</dd></div>
    </dl>
</div>
<div class="signatures three">
    <div class="sign"><b>{{ __('الفني') }}</b></div>
    <div class="sign"><b>{{ __('المستأجر — استلام العمل') }}</b></div>
    <div class="sign"><b>{{ __('مشرف الصيانة') }}</b></div>
</div>
{!! $footer !!}
