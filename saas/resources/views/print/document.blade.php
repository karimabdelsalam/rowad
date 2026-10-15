<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <link href="https://fonts.bunny.net/css?family=tajawal:400,700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 14mm; }
        body { font-family: 'Tajawal', system-ui, sans-serif; color: #111827; font-size: 14px; line-height: 1.7; margin: 0; }
        .sheet { max-width: 190mm; margin: 0 auto; padding: 8mm 0; }
        table.grid { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table.grid th, table.grid td { border: 1px solid #9ca3af; padding: 5px 8px; text-align: start; }
        table.grid th { background: #f3f4f6; }
        .toolbar { position: sticky; top: 0; background: #f8fafc; border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: center; }
        .toolbar button { font: inherit; padding: 6px 16px; border-radius: 8px; border: 1px solid #0f766e; background: #0f766e; color: #fff; cursor: pointer; }
        .pagebreak { page-break-before: always; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">{{ __('طباعة / حفظ PDF') }}</button></div>
    <div class="sheet">{!! $html !!}</div>
</body>
</html>
