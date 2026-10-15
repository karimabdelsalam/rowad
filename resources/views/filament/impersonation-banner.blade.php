<div style="background:#f59e0b;color:#1f2937;text-align:center;padding:6px;font-weight:700">
    {{ __('أنت تتصفح بحساب :name', ['name' => auth()->user()?->name]) }} —
    <a href="{{ route('portal.impersonate.stop') }}" style="text-decoration:underline">{{ __('العودة إلى لوحة الإدارة') }}</a>
</div>
