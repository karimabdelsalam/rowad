@php
    use App\Support\Branding;
    $rtl = app()->getLocale() !== 'en';
    $name = Branding::name();
    $logo = Branding::logoUrl();
    $paid = $payment->isPaid();
    $brand = Branding::color() ?: '#0f766e';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('سداد قسط') }} — {{ $name }}</title>
    @include('partials.favicons')
    <link href="{{ asset('fonts/cairo/cairo.css') }}" rel="stylesheet">
    @if (! $paid)
        <link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.15.0/moyasar.css">
        <script src="https://cdn.moyasar.com/mpf/1.15.0/moyasar.js"></script>
    @endif
    <style>
        :root { --brand: {{ $brand }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Cairo, system-ui, sans-serif; background: #f1f5f9; color: #0f172a; line-height: 1.7; }
        .wrap { max-width: 520px; margin: 0 auto; padding: 32px 16px 48px; }
        .brand { display: flex; align-items: center; gap: 12px; justify-content: center; margin-bottom: 20px; }
        .brand img { height: 48px; width: auto; max-width: 220px; object-fit: contain; }
        .brand b { font-size: 1.15rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 20px 50px rgb(15 23 42 / .08); overflow: hidden; }
        .head { padding: 22px 24px 18px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(180deg, #fbfcfc, #f6f9f9); }
        .head p { margin: 0; color: #64748b; font-size: .88rem; }
        .head h1 { margin: 2px 0 0; font-size: 1.3rem; font-weight: 800; }
        .amount { display: flex; align-items: baseline; gap: 8px; margin-top: 10px; }
        .amount b { font-size: 2.2rem; font-weight: 800; color: var(--brand); line-height: 1; }
        .amount span { color: #64748b; font-weight: 700; }
        .facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 18px 24px; }
        .fact { background: #f8fafc; border-radius: 12px; padding: 10px 12px; }
        .fact span { display: block; font-size: .74rem; color: #64748b; font-weight: 600; }
        .fact b { font-size: .95rem; }
        .form { padding: 6px 24px 24px; }
        .mysr-form { font-family: inherit; }
        .note { text-align: center; color: #64748b; font-size: .8rem; margin-top: 14px; }
        .state { padding: 40px 24px; text-align: center; }
        .state .ico { width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 14px; display: grid; place-items: center; }
        .state.ok .ico { background: #dcfce7; color: #166534; } .state.bad .ico { background: #fee2e2; color: #991b1b; }
        .state .ico svg { width: 36px; height: 36px; }
        .state h2 { margin: 0 0 6px; font-size: 1.3rem; font-weight: 800; }
        .state p { margin: 0; color: #64748b; }
        .btn { display: inline-block; margin-top: 18px; padding: 10px 22px; border-radius: 12px; background: var(--brand); color: #fff; font-weight: 800; text-decoration: none; }
        .credit { text-align: center; color: #94a3b8; font-size: .78rem; margin-top: 18px; }
        .credit a { color: var(--brand); font-weight: 700; text-decoration: none; }
        .methods { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; margin: 0 0 10px; }
        .methods span { font-size: .72rem; font-weight: 700; color: #475569; background: #f1f5f9; border-radius: 999px; padding: 3px 10px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        @if ($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<b>{{ $name }}</b>@endif
    </div>
    <div class="card">
        @if ($paid)
            <div class="state ok">
                <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></div>
                <h2>{{ __('تم السداد بنجاح') }}</h2>
                <p>{{ __('استلمنا :amount ر.س عن :unit. شكرًا لك.', ['amount' => number_format((float) $payment->amount, 2), 'unit' => $installment->unit?->title]) }}</p>
                @if ($payment->receiptVoucher)
                    <p style="margin-top:6px">{{ __('رقم سند القبض') }}: <b dir="ltr">{{ $payment->receiptVoucher->number }}</b></p>
                @endif
                @if (\App\Support\PlanLimits::allows('client_portal'))
                    <a class="btn" href="{{ url('/client') }}">{{ __('بوابة المستأجرين') }}</a>
                @endif
            </div>
        @else
            <div class="head">
                <p>{{ __('سداد قسط') }} · {{ $installment->contract?->number }}</p>
                <h1>{{ $installment->unit?->title }}</h1>
                <div class="amount"><b>{{ number_format((float) $payment->amount, 2) }}</b><span>{{ __('ر.س') }}</span></div>
            </div>
            <div class="facts">
                <div class="fact"><span>{{ __('المستأجر') }}</span><b>{{ $client?->displayName() ?? '—' }}</b></div>
                <div class="fact"><span>{{ __('تاريخ الاستحقاق') }}</span><b dir="ltr">{{ $installment->due_date?->format('Y-m-d') }}</b></div>
            </div>
            @if ($result === 'failed')
                <div class="state bad" style="padding:8px 24px 0">
                    <p style="color:#991b1b;font-weight:700">{{ __('لم تكتمل عملية الدفع. يمكنك المحاولة مرة أخرى.') }}</p>
                </div>
            @endif
            <div class="form">
                <div class="methods">
                    @foreach ($methods as $m)<span>{{ __(\App\Services\Payments\Payments::METHODS[$m]) }}</span>@endforeach
                </div>
                <div class="mysr-form"></div>
                <p class="note">{{ __('الدفع آمن ومشفّر عبر Moyasar. لا تُحفظ بيانات بطاقتك لدى المكتب.') }}</p>
            </div>
            <script>
                Moyasar.init({
                    element: '.mysr-form',
                    amount: @json($amountMinor),
                    currency: 'SAR',
                    description: @json(__('سداد قسط :unit — :office', ['unit' => $installment->unit?->title, 'office' => $name])),
                    publishable_api_key: @json($publishableKey),
                    callback_url: @json($callback),
                    methods: @json($methods),
                    metadata: { token: @json($payment->token), installment: @json($installment->id) },
                    language: @json($rtl ? 'ar' : 'en'),
                    supported_networks: ['mada', 'visa', 'mastercard'],
                    apple_pay: { country: 'SA', label: @json($name), validate_merchant_url: 'https://api.moyasar.com/v1/applepay/initiate' }
                });
            </script>
        @endif
    </div>
    <p class="credit">@include('partials.powered-by')</p>
</div>
</body>
</html>
