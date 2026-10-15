<x-filament-panels::page>
    <style>
        .sub-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px; padding: 26px 28px; border-radius: 20px; color: #fff;
            background: radial-gradient(1200px 300px at 10% -40%, rgb(255 255 255 / .18), transparent 60%), linear-gradient(135deg, #0f766e 0%, #115e59 55%, #134e4a 100%);
            box-shadow: 0 10px 30px rgb(15 118 110 / .25); }
        .sub-hero__kicker { margin: 0; font-size: .85rem; opacity: .85; }
        .sub-hero__plan { margin: 2px 0 8px; font-size: 1.7rem; font-weight: 800; line-height: 1.35; }
        .sub-hero__status { display: inline-flex; align-items: center; gap: 6px; padding: 3px 12px; border-radius: 999px; font-size: .85rem; font-weight: 700; background: rgb(255 255 255 / .16); }
        .sub-hero__status i { width: 8px; height: 8px; border-radius: 50%; background: #5eead4; }
        .sub-hero__status[data-color=warning] i { background: #fbbf24; } .sub-hero__status[data-color=danger] i { background: #f87171; } .sub-hero__status[data-color=info] i { background: #93c5fd; }
        .sub-hero__facts { display: flex; flex-wrap: wrap; gap: 12px; }
        .sub-hero__fact { background: rgb(255 255 255 / .1); border: 1px solid rgb(255 255 255 / .16); border-radius: 14px; padding: 10px 16px; min-width: 150px; }
        .sub-hero__fact span { display: block; font-size: .8rem; opacity: .8; }
        .sub-hero__fact b { font-size: 1.1rem; }
        .sub-grid { display: grid; grid-template-columns: 3fr 2fr; gap: 24px; align-items: start; }
        @media (max-width: 1024px) { .sub-grid { grid-template-columns: 1fr; } }
        .sub-card { background: #fff; border: 1px solid rgb(15 23 42 / .08); border-radius: 18px; padding: 22px 24px; box-shadow: 0 1px 2px rgb(15 23 42 / .04); }
        .dark .sub-card { background: rgb(255 255 255 / .04); border-color: rgb(255 255 255 / .1); }
        .sub-card h3 { margin: 0 0 4px; font-size: 1.05rem; font-weight: 800; }
        .sub-card .hint { margin: 0 0 18px; font-size: .85rem; color: rgb(100 116 139); }
        .quota { margin-bottom: 18px; } .quota:last-child { margin-bottom: 0; }
        .quota__row { display: flex; justify-content: space-between; gap: 12px; font-weight: 700; margin-bottom: 6px; font-size: .95rem; }
        .quota__row small { font-weight: 600; color: rgb(100 116 139); font-variant-numeric: tabular-nums; }
        .quota__bar { height: 8px; border-radius: 999px; background: rgb(15 23 42 / .07); overflow: hidden; }
        .dark .quota__bar { background: rgb(255 255 255 / .1); }
        .quota__bar span { display: block; height: 100%; border-radius: 999px; background: #0f766e; }
        .quota[data-level=near] .quota__bar span { background: #d97706; }
        .quota[data-level=full] .quota__bar span { background: #dc2626; }
        .quota__note { margin: 6px 0 0; font-size: .82rem; font-weight: 600; }
        .quota[data-level=near] .quota__note { color: #b45309; } .quota[data-level=full] .quota__note { color: #b91c1c; }
        .features { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .features li { display: flex; align-items: center; gap: 10px; font-weight: 600; }
        .features li.off { color: rgb(148 163 184); }
        .sub-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
        .features svg { width: 20px; height: 20px; flex: none; }
        .features li.on svg { color: #0f766e; }
        .sub-request { margin-top: 16px; border-radius: 14px; padding: 14px 16px; background: rgb(240 253 250); border: 1px solid rgb(204 251 241); color: #134e4a; font-size: .92rem; }
        .dark .sub-request { background: rgb(20 184 166 / .1); border-color: rgb(20 184 166 / .25); color: #99f6e4; }
    </style>

    <div class="sub-hero">
        <div>
            <p class="sub-hero__kicker">{{ __('باقتك الحالية') }}</p>
            <h2 class="sub-hero__plan">{{ $planName }}</h2>
            <span class="sub-hero__status" data-color="{{ $statusColor }}"><i></i>{{ $status }}</span>
        </div>
        <div class="sub-hero__facts">
            @if ($endsAt)
                <div class="sub-hero__fact"><span>{{ $endsLabel }}</span><b>{{ $endsAt->format('Y-m-d') }}</b>
                    @if ($endsAt->isFuture())<span>{{ trans_choice('{1} بعد يوم واحد|[2,*] بعد :count يومًا', max(1, (int) today()->diffInDays($endsAt->copy()->startOfDay())), ['count' => max(1, (int) today()->diffInDays($endsAt->copy()->startOfDay()))]) }}</span>@endif
                </div>
            @endif
            @if ($plan)
                <div class="sub-hero__fact"><span>{{ __('السعر') }}</span><b>{{ number_format((float) $plan->monthly_price) }} {{ __('ر.س / شهريًا') }}</b></div>
            @endif
        </div>
    </div>

    <div class="sub-grid">
        <section class="sub-card">
            <h3>{{ __('الاستهلاك') }}</h3>
            <p class="hint">{{ __('ما استخدمته من حدود باقتك.') }}@if ($storageMeasuredAt) {{ __('يُقاس التخزين كل ساعة.') }}@endif</p>
            @foreach ($quotas as $quota)
                <div class="quota" data-level="{{ $quota['level'] }}">
                    <div class="quota__row">
                        <span>{{ $quota['label'] }}</span>
                        <small>{{ __(':used من :limit', ['used' => rtrim(rtrim(number_format($quota['used'], 1), '0'), '.'), 'limit' => $quota['limit'] === null ? __('غير محدود') : number_format($quota['limit'])]) }}</small>
                    </div>
                    <div class="quota__bar" role="progressbar" aria-label="{{ $quota['label'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $quota['percent'] ?? 0 }}">
                        <span style="width: {{ $quota['percent'] ?? 4 }}%; {{ $quota['percent'] === null ? 'opacity:.35' : '' }}"></span>
                    </div>
                    @if ($quota['level'] === 'full')
                        <p class="quota__note">{{ __('وصلت إلى الحد. اطلب الترقية للإضافة.') }}</p>
                    @elseif ($quota['level'] === 'near')
                        <p class="quota__note">{{ __('اقتربت من الحد.') }}</p>
                    @endif
                </div>
            @endforeach
        </section>

        <section class="sub-card">
            <h3>{{ __('مزايا الباقة') }}</h3>
            <p class="hint">{{ __('الخدمات المشمولة في باقتك الحالية.') }}</p>
            <ul class="features">
                @foreach ($features as $feature)
                    <li class="{{ $feature['on'] ? 'on' : 'off' }}">
                        @if ($feature['on'])
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        @endif
                        <span>{{ $feature['label'] }}@unless ($feature['on'])<span class="sub-sr"> — {{ __('غير مشمولة') }}</span>@endunless</span>
                    </li>
                @endforeach
            </ul>
            @if ($request)
                <div class="sub-request">
                    {{ __('طلبت الترقية إلى :plan بتاريخ :date، وسيتواصل معك فريقنا.', ['plan' => $request['plan'], 'date' => \Illuminate\Support\Carbon::parse($request['at'])->format('Y-m-d')]) }}
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
