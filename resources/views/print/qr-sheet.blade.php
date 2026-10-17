@php
    use App\Support\Settings;
    use App\Support\Website;
    $logo = Settings::get('logo') ? route('site.logo') : null;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.favicons')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $label }} — {{ Website::name() }}</title>
    <link href="{{ asset('fonts/cairo/cairo.css') }}" rel="stylesheet">
    <style>
        @page { size: A4; margin: 0; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font-family: 'Cairo', system-ui, sans-serif; color: #0f172a; background: #eef1f4; }
        .sheet { width: 210mm; height: 297mm; margin: 16px auto; background: #fff; box-shadow: 0 10px 40px rgb(15 23 42 / .12); position: relative; overflow: hidden;
            display: flex; flex-direction: column; align-items: center; text-align: center; padding: 26mm 22mm 18mm; }
        .sheet::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 14mm; background: linear-gradient(90deg, #0f766e, #14b8a6); }
        .logo { height: 26mm; width: auto; margin-top: 8mm; }
        .org { font-size: 26pt; font-weight: 800; margin: 6mm 0 0; color: #0f766e; }
        .label { display: inline-block; margin-top: 8mm; padding: 2mm 8mm; border-radius: 999px; background: #f0fdfa; color: #0f766e; font-weight: 800; font-size: 16pt; border: 1px solid #ccfbf1; }
        .prompt { font-size: 17pt; font-weight: 600; line-height: 1.7; margin: 10mm auto 0; max-width: 150mm; color: #334155; }
        .qr { margin-top: 12mm; padding: 6mm; border: 2px solid #e2e8f0; border-radius: 8mm; }
        .qr img { width: 90mm; height: 90mm; display: block; }
        .url { margin-top: 6mm; font-size: 12pt; color: #64748b; direction: ltr; }
        .credit { margin-top: auto; font-size: 9pt; color: #94a3b8; }
        .credit a { color: #0f766e; text-decoration: none; font-weight: 700; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; justify-content: center; gap: 8px; padding: 8px; background: rgb(255 255 255 / .9); border-bottom: 1px solid #e2e8f0; }
        .toolbar button { font: inherit; font-weight: 700; padding: 6px 18px; border-radius: 10px; border: 1px solid #0f766e; background: #0f766e; color: #fff; cursor: pointer; }
        .toolbar button.ghost { background: #fff; color: #0f766e; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; box-shadow: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('طباعة') }}</button>
        <button type="button" class="ghost" onclick="RowadPdf.save(document.querySelector('.sheet'), @js(\Illuminate\Support\Str::slug($label, '-', null) ?: 'qr'))">{{ __('تحميل PDF') }}</button>
    </div>
    <script src="{{ asset('vendor/pdf/rowad-pdf.js') }}"></script>
    <div class="sheet">
        @if ($logo)<img class="logo" src="{{ $logo }}" alt="{{ Website::name() }}">@endif
        <h1 class="org">{{ Website::name() }}</h1>
        <div class="label">{{ $label }}</div>
        <p class="prompt">{{ $prompt }}</p>
        <div class="qr"><img src="{{ $qr }}" alt="QR"></div>
        <div class="url">{{ $url }}</div>
        <div class="credit">@include('partials.powered-by')</div>
    </div>
</body>
</html>
