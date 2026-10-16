<div x-data="{ copied: false, url: @js($url) }" style="display:grid;gap:10px">
    <p style="margin:0;font-size:.9rem;color:#64748b">{{ __('المبلغ') }}: <b style="color:#0f172a">{{ number_format((float) $amount, 2) }} {{ __('ر.س') }}</b></p>
    <div style="display:flex;gap:8px;align-items:center">
        <input type="text" readonly value="{{ $url }}" dir="ltr" x-ref="link" style="flex:1;font-family:ui-monospace,monospace;font-size:.85rem;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#0f172a">
        <button type="button" x-on:click="navigator.clipboard.writeText(url).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
            style="padding:9px 14px;border-radius:10px;border:0;background:#0f766e;color:#fff;font-weight:700;cursor:pointer;white-space:nowrap">
            <span x-show="! copied">{{ __('نسخ') }}</span><span x-show="copied" x-cloak>{{ __('نُسخ ✓') }}</span>
        </button>
    </div>
    <a href="{{ $url }}" target="_blank" rel="noopener" style="font-size:.85rem;color:#0f766e;font-weight:700">{{ __('فتح صفحة الدفع') }} ↗</a>
</div>
