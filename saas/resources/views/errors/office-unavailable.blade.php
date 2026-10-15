<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $tenant->name }}</title>
<style>body{font-family:Cairo,system-ui,sans-serif;background:#f8fafc;color:#0f172a;display:grid;place-items:center;min-height:100vh;margin:0}main{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:32px;max-width:480px;text-align:center}</style></head>
<body><main>
    <h1>{{ $tenant->name }}</h1>
    <p>{{ __('الخدمة متوقفة مؤقتًا لهذا المكتب. يرجى التواصل مع إدارة المنصة لتجديد الاشتراك.') }}</p>
</main></body></html>
