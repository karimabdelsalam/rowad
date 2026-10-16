<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('التحقق من العقد') }}</title>
<style>body{font-family:Cairo,system-ui,sans-serif;background:#f8fafc;display:grid;place-items:center;min-height:100vh;margin:0}main{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px;max-width:460px;width:calc(100% - 32px)}.ok{color:#047857;font-weight:800}</style></head>
<body><main>
    <p class="ok">✓ {{ __('عقد صادر من :office', ['office' => \App\Support\Website::name()]) }}</p>
    <p>{{ __('رقم العقد') }}: <strong>{{ $contract->number }}</strong></p>
    <p>{{ __('الوحدة') }}: {{ $contract->unit->title }}</p>
    <p>{{ __('الأطراف') }}: {{ $contract->clients->pluck('full_name')->join('، ') }}</p>
    @isset($contract->start_date)<p>{{ __('المدة') }}: {{ $contract->start_date->toDateString() }} — {{ $contract->end_date->toDateString() }}</p>@endisset
    <p>{{ __('الحالة') }}: {{ $contract->status->getLabel() }}</p>
    @if ($contract instanceof \App\Models\RentContract && ($signatures = $contract->signatures()->orderBy('id')->get())->isNotEmpty())
        <p>{{ __('التوقيع الإلكتروني') }}:</p>
        <ul>
            @foreach ($signatures as $s)
                <li>{{ $s->roleLabel() }}: {{ $s->name }} — {{ $s->statusLabel() }}@if ($s->signed_at) ({{ $s->signed_at->format('Y-m-d H:i') }})@endif</li>
            @endforeach
        </ul>
        @if ($contract->signed_at)
            <p class="ok">✓ {{ __('العقد موقّع إلكترونيًا من جميع الأطراف') }}</p>
        @endif
    @endif
</main></body></html>
