<x-filament-panels::page>
    @php
        $p = $state['payload'] ?? [];
        $color = ['valid' => 'success', 'grace' => 'warning', 'invalid' => 'danger'][$state['state']];
        $label = ['valid' => __('صالح'), 'grace' => __('يعمل دون تحقق مؤقتًا'), 'invalid' => __('غير صالح')][$state['state']];
        $limit = fn ($v) => $v === null ? __('غير محدود') : number_format($v);
    @endphp
    <x-filament::section>
        <x-slot name="heading">{{ __('حالة الترخيص') }} <x-filament::badge :color="$color" style="display:inline-flex;margin-inline-start:8px">{{ $label }}</x-filament::badge></x-slot>
        @if ($state['reason'])
            <p style="margin:0 0 14px;font-weight:600">{{ \App\Services\Licensing\LicenseClient::reasonText($state['reason']) }}@if ($state['days_offline']) — {{ __('آخر تحقق ناجح قبل :d يومًا', ['d' => $state['days_offline']]) }}@endif</p>
        @endif
        <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px 24px;margin:0">
            @foreach ([
                __('العميل') => $p['customer'] ?? '-',
                __('النطاق') => $domain,
                __('المفتاح') => $p['key_hint'] ?? ($stored['key'] ?? '-'),
                __('ينتهي في') => filled($p['expires_at'] ?? null) ? substr($p['expires_at'], 0, 10) : ($p ? __('لا ينتهي') : '-'),
                __('الدعم حتى') => filled($p['support_until'] ?? null) ? substr($p['support_until'], 0, 10) : '-',
                __('المؤسسات المسموحة') => $p ? $limit($p['limits']['offices'] ?? null) : '-',
                __('الوحدات') => $p ? $limit($p['limits']['units'] ?? null) : '-',
                __('المستخدمون') => $p ? $limit($p['limits']['users'] ?? null) : '-',
                __('آخر تحقق') => filled($stored['last_ok_at'] ?? null) ? substr($stored['last_ok_at'], 0, 16) : '-',
                __('سيرفر التراخيص') => $server,
            ] as $term => $value)
                <div><dt style="font-size:.8rem;color:var(--rowad-muted)">{{ $term }}</dt><dd style="margin:2px 0 0;font-weight:700" dir="auto">{{ $value }}</dd></div>
            @endforeach
        </dl>
        @if (! empty($p['features']))
            <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:6px">
                @foreach ($p['features'] as $f)
                    <x-filament::badge color="gray">{{ __(\App\Support\PlanLimits::FEATURES[$f] ?? $f) }}</x-filament::badge>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
