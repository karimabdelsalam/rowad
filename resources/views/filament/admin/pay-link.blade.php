<div x-data="{ copied: false, url: @js($url) }" style="display:grid;gap:10px">
    <p style="margin:0;font-size:.9rem;color:#64748b">{{ __('المبلغ') }}: <b style="color:#0f172a">{{ number_format((float) $amount, 2) }} {{ __('ر.س') }}</b></p>
    <div style="display:flex;gap:8px;align-items:center">
        <input type="text" readonly value="{{ $url }}" dir="ltr" x-ref="link" style="flex:1;font-family:ui-monospace,monospace;font-size:.85rem;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#0f172a">
        <button type="button" x-on:click="navigator.clipboard.writeText(url).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
            style="padding:9px 14px;border-radius:10px;border:0;background:#0f766e;color:#fff;font-weight:700;cursor:pointer;white-space:nowrap">
            <span x-show="! copied">{{ __('نسخ') }}</span><span x-show="copied" x-cloak>{{ __('نُسخ ✓') }}</span>
        </button>
    </div>
    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        @if ($whatsapp ?? null)
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;background:#16a34a;color:#fff;font-weight:700;font-size:.88rem">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.3c.2-.6.2-1.1.2-1.2-.1-.1-.2-.2-.5-.3Z"/></svg>
                {{ __('إرسال عبر واتساب') }}
            </a>
        @endif
        <a href="{{ $url }}" target="_blank" rel="noopener" style="font-size:.85rem;color:#0f766e;font-weight:700">{{ __('فتح صفحة الدفع') }} ↗</a>
    </div>
</div>
