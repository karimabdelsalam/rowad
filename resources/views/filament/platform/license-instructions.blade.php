@php
    $public = filled(config('rowad.license.signing_key')) ? \App\Services\Licensing\LicenseToken::publicKeyFromSecret(config('rowad.license.signing_key')) : null;
    $env = "LICENSE_MODE=self_hosted\nLICENSE_KEY={$license->key}\nLICENSE_SERVER=".rtrim((string) config('app.url'), '/')."\nLICENSE_PUBLIC_KEY=".($public ?? '<'.__('شغّل rowad:license:keys أولًا').'>');
@endphp
<div style="display:grid;gap:14px">
    <p style="margin:0">{{ __('على سيرفر العميل، بعد تثبيت النظام، أضف إلى ملف .env:') }}</p>
    <pre dir="ltr" style="margin:0;padding:14px;border-radius:12px;background:#0f172a;color:#e2e8f0;font-size:.85rem;white-space:pre-wrap;word-break:break-all">{{ $env }}</pre>
    <p style="margin:0">{{ __('ثم نفّذ:') }}</p>
    <pre dir="ltr" style="margin:0;padding:14px;border-radius:12px;background:#0f172a;color:#e2e8f0;font-size:.85rem">php artisan optimize
php artisan rowad:license:activate</pre>
    <p style="margin:0;color:var(--rowad-muted);font-size:.9rem">{{ __('تتحقق النسخة من الترخيص يوميًا، وتستمر بالعمل حتى 3 أسابيع إن انقطع الاتصال. الإيقاف أو الإلغاء من هنا يصل إليها في التحقق التالي.') }}</p>
</div>
