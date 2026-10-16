@php
    use App\Support\Branding;
    use App\Models\ContractSignature;
    $rtl = app()->getLocale() !== 'en';
    $name = Branding::name();
    $logo = Branding::logoUrl();
    $brand = Branding::color() ?: '#0f766e';
    $open = $signature->status === ContractSignature::PENDING && $signature->expires_at->isFuture();
    // step 2 once a code is on its way (also after a reload, while that code is still valid)
    $askCode = session('code_sent') || old('code') !== null || $errors->hasAny(['code', 'signature', 'agree']) || $signature->otp_expires_at?->isFuture();
    $frame = view('print.document', ['html' => $document, 'title' => $contract->number, 'embedded' => true])->render();
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('توقيع العقد :number', ['number' => $contract->number]) }} — {{ $name }}</title>
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800&display=swap" rel="stylesheet">
    <style>
        :root { --brand: {{ $brand }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Cairo, system-ui, sans-serif; background: #f1f5f9; color: #0f172a; line-height: 1.7; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 28px 16px 48px; display: grid; gap: 16px; }
        .brand { display: flex; align-items: center; gap: 12px; justify-content: center; }
        .brand img { height: 46px; width: auto; max-width: 220px; object-fit: contain; }
        .brand b { font-size: 1.1rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 20px 50px rgb(15 23 42 / .07); overflow: hidden; }
        .head { padding: 20px 22px; border-bottom: 1px solid #e2e8f0; background: linear-gradient(180deg, #fbfcfc, #f4f8f8); }
        .head p { margin: 0; color: #64748b; font-size: .86rem; }
        .head h1 { margin: 2px 0 0; font-size: 1.25rem; font-weight: 800; }
        .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; padding: 16px 22px; }
        .fact { background: #f8fafc; border-radius: 12px; padding: 9px 12px; }
        .fact span { display: block; font-size: .72rem; color: #64748b; font-weight: 600; }
        .fact b { font-size: .92rem; }
        .parties { padding: 0 22px 18px; display: grid; gap: 8px; }
        .party { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 12px; font-size: .88rem; }
        .party small { color: #64748b; }
        .party.is-me { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 6%, #fff); }
        .chip { font-size: .72rem; font-weight: 700; border-radius: 999px; padding: 2px 10px; background: #f1f5f9; color: #475569; white-space: nowrap; }
        .chip.ok { background: #dcfce7; color: #166534; } .chip.bad { background: #fee2e2; color: #991b1b; }
        details.doc summary { cursor: pointer; padding: 14px 22px; font-weight: 800; color: var(--brand); list-style: none; }
        details.doc summary::-webkit-details-marker { display: none; }
        details.doc iframe { display: block; width: 100%; height: 900px; border: 0; border-top: 1px solid #e2e8f0; background: #eef2f6; }
        .section { padding: 20px 22px; }
        .section h2 { margin: 0 0 4px; font-size: 1.05rem; font-weight: 800; }
        .section p { margin: 0 0 12px; color: #475569; font-size: .9rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font: inherit; font-weight: 800; padding: 11px 22px; border-radius: 12px; border: 0; background: var(--brand); color: #fff; cursor: pointer; width: 100%; font-size: 1rem; }
        .btn.ghost { background: #fff; color: #475569; border: 1px solid #cbd5e1; width: auto; padding: 6px 14px; font-size: .82rem; }
        .btn:disabled { opacity: .6; cursor: not-allowed; }
        label.field { display: grid; gap: 4px; font-weight: 700; font-size: .88rem; margin-bottom: 12px; }
        input.code { font: inherit; font-size: 1.4rem; letter-spacing: .5em; text-align: center; padding: 10px; border: 1px solid #cbd5e1; border-radius: 12px; direction: ltr; }
        .pad { position: relative; border: 2px dashed #cbd5e1; border-radius: 16px; background: #fff; touch-action: none; }
        .pad canvas { display: block; width: 100%; height: 200px; border-radius: 14px; cursor: crosshair; }
        .pad .hint { position: absolute; inset: 0; display: grid; place-items: center; color: #94a3b8; font-size: .9rem; pointer-events: none; }
        .pad-tools { display: flex; justify-content: space-between; align-items: center; margin: 6px 0 12px; font-size: .78rem; color: #64748b; }
        .agree { display: flex; gap: 10px; align-items: flex-start; font-size: .86rem; color: #334155; margin-bottom: 14px; }
        .agree input { margin-top: 5px; width: 18px; height: 18px; accent-color: var(--brand); flex: none; }
        .alert { border-radius: 12px; padding: 10px 14px; font-size: .88rem; font-weight: 700; }
        .alert.ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .alert.bad { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .state { padding: 34px 22px; text-align: center; }
        .state .ico { width: 70px; height: 70px; border-radius: 50%; margin: 0 auto 12px; display: grid; place-items: center; background: #dcfce7; color: #166534; }
        .state.bad .ico { background: #fee2e2; color: #991b1b; }
        .state svg { width: 34px; height: 34px; }
        .state h2 { margin: 0 0 4px; font-size: 1.25rem; font-weight: 800; }
        .state p { margin: 0; color: #64748b; }
        .decline { padding: 0 22px 20px; font-size: .84rem; }
        .decline summary { cursor: pointer; color: #64748b; }
        .decline textarea { width: 100%; font: inherit; border: 1px solid #cbd5e1; border-radius: 12px; padding: 8px 10px; margin: 8px 0; }
        .legal { text-align: center; color: #94a3b8; font-size: .75rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        @if ($logo)<img src="{{ $logo }}" alt="{{ $name }}">@endif
        <b>{{ $name }}</b>
    </div>

    @if (session('status'))
        <div class="alert ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert bad">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="head">
            <p>{{ __('توقيع إلكتروني لعقد إيجار') }} · {{ $contract->number }}</p>
            <h1>{{ $contract->unit->title }}</h1>
        </div>
        <div class="facts">
            <div class="fact"><span>{{ __('المدة') }}</span><b><bdi>{{ $contract->start_date->format('Y-m-d') }}</bdi> ← <bdi>{{ $contract->end_date->format('Y-m-d') }}</bdi></b></div>
            <div class="fact"><span>{{ __('قيمة الدفعة') }}</span><b>{{ number_format((float) $contract->rent_amount, 2) }} · {{ $contract->payment_cycle?->getLabel() }}</b></div>
            <div class="fact"><span>{{ __('توقّع بصفتك') }}</span><b>{{ $signature->roleLabel() }} — {{ $signature->name }}</b></div>
        </div>
        <div class="parties">
            @foreach ($parties as $party)
                <div @class(['party', 'is-me' => $party->is($signature)])>
                    <div><b>{{ $party->name }}</b> <small>· {{ $party->roleLabel() }}</small></div>
                    <span @class(['chip', 'ok' => $party->isSigned(), 'bad' => $party->status === ContractSignature::DECLINED])>
                        {{ $party->statusLabel() }}@if ($party->signed_at) · <bdi>{{ $party->signed_at->format('Y-m-d') }}</bdi>@endif
                    </span>
                </div>
            @endforeach
        </div>
        <details class="doc" @if (! $open) open @endif>
            <summary>{{ __('اقرأ العقد كاملًا قبل التوقيع ▾') }}</summary>
            <iframe title="{{ $contract->number }}" srcdoc="{{ $frame }}" loading="lazy"
                onload="try { this.style.height = (this.contentDocument.documentElement.scrollHeight + 20) + 'px' } catch (e) {}"></iframe>
        </details>
    </div>

    <div class="card">
        @if ($signature->isSigned())
            <div class="state">
                <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></div>
                <h2>{{ __('تم توقيعك على العقد') }}</h2>
                <p>{{ __('بتاريخ :at — سيصلك العقد الموقّع من المكتب بعد اكتمال توقيع جميع الأطراف.', ['at' => $signature->signed_at->format('Y-m-d H:i')]) }}</p>
            </div>
        @elseif (! $open)
            <div class="state bad">
                <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg></div>
                <h2>{{ $signature->status === ContractSignature::DECLINED ? __('رفضت توقيع العقد') : __('انتهت صلاحية رابط التوقيع') }}</h2>
                <p>{{ __('تواصل مع المكتب لأي استفسار.') }}</p>
            </div>
        @elseif (! $askCode)
            <div class="section">
                <h2>{{ __('١. تأكيد هويتك') }}</h2>
                <p>{{ __('سنرسل رمز تحقق إلى جوالك المسجل لدى المكتب (:m) لتأكيد أنك من يوقّع.', ['m' => $signature->maskedMobile()]) }}</p>
                <form method="post" action="{{ url('/sign/'.$token.'/code') }}">
                    @csrf
                    <button class="btn" type="submit">{{ __('أرسل رمز التحقق') }}</button>
                </form>
            </div>
        @else
            <form class="section" method="post" action="{{ url('/sign/'.$token) }}" id="sign-form">
                @csrf
                <h2>{{ __('٢. وقّع العقد') }}</h2>
                <label class="field">{{ __('رمز التحقق المرسل إلى جوالك') }}
                    <input class="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" value="{{ old('code') }}" required>
                </label>
                <div class="field" style="display:block;font-weight:700;font-size:.88rem;margin-bottom:4px">{{ __('توقيعك') }}</div>
                <div class="pad"><canvas id="pad"></canvas><div class="hint" id="pad-hint">{{ __('ارسم توقيعك هنا بإصبعك') }}</div></div>
                <div class="pad-tools"><span>{{ __('اسم الموقّع: :name', ['name' => $signature->name]) }}</span><button class="btn ghost" type="button" id="pad-clear">{{ __('مسح') }}</button></div>
                <input type="hidden" name="signature" id="signature">
                <label class="agree"><input type="checkbox" name="agree" value="1" required>
                    <span>{{ __('أقر بأنني اطلعت على العقد وأوافق على جميع بنوده، وأن توقيعي الإلكتروني هذا ملزم لي كتوقيعي الخطي.') }}</span></label>
                <button class="btn" type="submit" id="sign-submit">{{ __('توقيع العقد') }}</button>
            </form>
            <form method="post" action="{{ url('/sign/'.$token.'/code') }}" style="text-align:center;margin:-8px 0 14px">
                @csrf
                <button class="btn ghost" type="submit">{{ __('لم يصلك الرمز؟ أعد الإرسال') }}</button>
            </form>
        @endif
        @if ($open)
            <details class="decline">
                <summary>{{ __('لا أوافق على العقد') }}</summary>
                <form method="post" action="{{ url('/sign/'.$token.'/decline') }}">
                    @csrf
                    <textarea name="reason" rows="2" maxlength="250" required placeholder="{{ __('ما الذي تريد تعديله؟') }}"></textarea>
                    <button class="btn ghost" type="submit">{{ __('إرسال الرفض للمكتب') }}</button>
                </form>
            </details>
        @endif
    </div>
    <p class="legal">{{ __('يُحفظ مع التوقيع تاريخه ووقته وعنوان الجهاز وبصمة بنود العقد كإثبات.') }}</p>
</div>
@if ($open && $askCode)
<script>
(() => {
    const canvas = document.getElementById('pad'), hint = document.getElementById('pad-hint'), form = document.getElementById('sign-form');
    const ctx = canvas.getContext('2d');
    let drawing = false, dirty = false;
    const size = () => {
        const r = canvas.getBoundingClientRect(), ratio = window.devicePixelRatio || 1;
        canvas.width = r.width * ratio; canvas.height = r.height * ratio;
        ctx.scale(ratio, ratio); ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
        dirty = false; hint.style.display = '';
    };
    const at = (e) => { const r = canvas.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
    canvas.addEventListener('pointerdown', (e) => { drawing = true; canvas.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.moveTo(...at(e)); });
    canvas.addEventListener('pointermove', (e) => { if (!drawing) return; ctx.lineTo(...at(e)); ctx.stroke(); dirty = true; hint.style.display = 'none'; });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((t) => canvas.addEventListener(t, () => drawing = false));
    document.getElementById('pad-clear').addEventListener('click', size);
    form.addEventListener('submit', (e) => {
        if (!dirty) { e.preventDefault(); alert(@js(__('ارسم توقيعك في المربع'))); return; }
        document.getElementById('signature').value = canvas.toDataURL('image/png');
        document.getElementById('sign-submit').disabled = true;
    });
    size();
})();
</script>
@endif
</body>
</html>
