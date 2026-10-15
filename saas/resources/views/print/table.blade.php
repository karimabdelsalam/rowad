<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: 'Cairo', system-ui, sans-serif; color: #111827; font-size: 12px; margin: 0; padding: 8mm; }
        header { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #0f766e; margin-bottom: 8px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #9ca3af; padding: 4px 6px; text-align: start; vertical-align: top; }
        th { background: #f3f4f6; }
        tr:nth-child(even) td { background: #fafafa; }
        thead { display: table-header-group; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <header>
        <h1>{{ \App\Support\Settings::get('site_name') ?? tenant('name') }} — {{ $title }}</h1>
        <span class="muted">{{ now()->format('Y-m-d H:i') }} · {{ count($rows) }}</span>
    </header>
    <table>
        <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>@foreach ($row as $cell)<td>{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($headings) }}" class="muted">{{ __('لا توجد بيانات') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @if ($truncated)
        <p class="muted">{{ __('تُطبع أول :count صفًا فقط؛ استخدم تصدير Excel للقائمة كاملة.', ['count' => count($rows)]) }}</p>
    @endif
</body>
</html>
