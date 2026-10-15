@extends('platform.layout')

@php
    $icons = [
        'building' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
        'contract' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        'money' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
        'scale' => 'M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z',
        'receipt' => 'm9 14.25 6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
        'chart' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
        'chat' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
        'printer' => 'M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z',
        'globe' => 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418',
        'phone' => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
        'shield' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
        'sync' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
        'users' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
        'check' => 'm4.5 12.75 6 6 9-13.5',
    ];
    $features = [
        ['building', 'العقارات والوحدات', 'المباني والوحدات بصورها وصكوكها وحدودها وعداداتها، وحالة كل وحدة لحظيًا: متاحة، مؤجرة، مباعة.'],
        ['contract', 'عقود الإيجار والبيع', 'عقد في دقيقة بجدول دفعات تلقائي حسب دورة السداد، بالتقويم الهجري أو الميلادي، مع التجديد والإنهاء والأرشفة.'],
        ['money', 'التحصيل والسندات', 'سندات قبض وصرف، تحصيل كامل أو جزئي، وتنبيهات تلقائية للمستأجر قبل الاستحقاق وبعد التأخر.'],
        ['scale', 'حسابات الملاك', 'توزيع كل دفعة على الملاك حسب الحصص، خصم العمولة والضرائب ورسوم الإدارة، وكشف حساب جاهز للتسوية.'],
        ['receipt', 'الفوترة الإلكترونية', 'فواتير ضريبية برمز QR وفق المرحلة الأولى من الفوترة الإلكترونية لهيئة الزكاة والضريبة والجمارك.'],
        ['chart', 'تقارير لحظية', 'الإيرادات والمصروفات والأرباح، أداء الوحدات، كشف حساب المستأجر والعقار، مع التصدير إلى Excel والطباعة.'],
        ['printer', 'مطبوعات احترافية', 'عقود وسندات وفواتير وخطابات بتصميم حديث وهوية مؤسستك، ومحرر قوالب لتعديل أي نموذج.'],
        ['chat', 'الرسائل والتنبيهات', 'رسائل SMS وبريد للمستأجرين والملاك، رسائل جماعية، وقوالب إشعارات قابلة للتعديل.'],
        ['globe', 'موقع خاص لمؤسستك', 'صفحة عامة لعرض وحداتك المتاحة واستقبال طلبات المعاينة مباشرة في صندوق الوارد.'],
    ];
    $featureLabels = \App\Support\PlanLimits::FEATURES;
    $allFeatures = array_keys($featureLabels);
    $limit = fn ($value, string $unit) => $value === null ? __('غير محدود') : number_format($value).' '.__($unit);
@endphp

@section('styles')
    .ai-sec { background: radial-gradient(900px 400px at 85% 10%, rgb(20 184 166 / .10), transparent 60%); }
    .ai-mock { background: #fff; border: 1px solid var(--line); border-radius: 22px; box-shadow: var(--shadow); padding: 22px; display: grid; gap: 14px; }
    .ai-mock__q { justify-self: start; background: var(--brand); color: #fff; padding: 10px 16px; border-radius: 16px; font-weight: 600; max-width: 85%; }
    .ai-mock__a { display: flex; gap: 12px; align-items: flex-start; font-size: .95rem; }
    .ai-mock__a p { margin: 0 0 10px; }
    .ai-mock__mark { width: 34px; height: 34px; flex: none; border-radius: 11px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #14b8a6, #0f766e); }
    .ai-mock table { width: 100%; border-collapse: collapse; font-size: .88rem; margin-bottom: 10px; }
    .ai-mock th { background: var(--soft); text-align: start; }
    .ai-mock th, .ai-mock td { border: 1px solid var(--line); padding: 6px 10px; }
    .ai-mock__tip { color: var(--brand-ink); background: var(--brand-soft); border: 1px solid var(--brand-line); border-radius: 12px; padding: 8px 12px; }
    .demo-cards .portal { display: flex; flex-direction: column; }
    .demo-cards .portal p { flex: 1; }
    .demo-btn { margin-top: 10px; }
    .demo-cred { display: block; text-align: center; color: rgb(255 255 255 / .85); margin-top: 10px; font-size: .82rem; position: relative; }
    .demo-btn { position: relative; }
    .hero { position: relative; overflow: hidden; padding: 72px 0 0; background:
        radial-gradient(900px 420px at 85% -10%, rgb(20 184 166 / .16), transparent 60%),
        radial-gradient(700px 380px at 0% 10%, rgb(235 104 52 / .08), transparent 60%), #fff; }
    .hero-grid { display: grid; grid-template-columns: 1fr; gap: 40px; text-align: center; }
    .hero h1 { font-size: clamp(2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.01em; margin: 18px auto 16px; max-width: 900px; }
    .hero h1 .hl { white-space: nowrap; background: linear-gradient(90deg, var(--brand), #14b8a6); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .hero .lead { font-size: clamp(1.05rem, 2vw, 1.25rem); color: var(--muted); max-width: 720px; margin: 0 auto 28px; }
    .hero .cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .hero .note { margin-top: 14px; color: var(--muted); font-size: .92rem; display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; }
    .hero .note span { display: inline-flex; align-items: center; gap: 6px; }
    .hero .note .icon { width: 18px; height: 18px; color: var(--brand); }
    .browser { margin: 48px auto 0; max-width: 1080px; border-radius: 18px 18px 0 0; background: #fff; border: 1px solid var(--line); border-bottom: 0;
        box-shadow: 0 30px 80px rgb(15 23 42 / .18); overflow: hidden; }
    .browser .chrome { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: var(--soft); border-bottom: 1px solid var(--line); direction: ltr; }
    .browser .chrome i { width: 11px; height: 11px; border-radius: 50%; background: #e2e8f0; display: block; }
    .browser .chrome i:nth-child(1) { background: #fca5a5; } .browser .chrome i:nth-child(2) { background: #fcd34d; } .browser .chrome i:nth-child(3) { background: #86efac; }
    .browser .chrome .url { margin-inline-start: 12px; flex: 1; max-width: 360px; background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 3px 12px; font-size: .8rem; color: var(--muted); }
    .browser img { width: 100%; height: auto; }

    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
    .stat { text-align: center; padding: 8px; }
    .stat b { display: block; font-size: 2rem; font-weight: 800; color: var(--ink); }
    .stat span { color: var(--muted); }

    .features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .feature { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 26px; box-shadow: var(--shadow); transition: transform .2s, box-shadow .2s; }
    .feature:hover { transform: translateY(-3px); box-shadow: 0 18px 40px rgb(15 23 42 / .1); }
    .feature .ico { width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; background: var(--brand-soft); color: var(--brand); border: 1px solid var(--brand-line); margin-bottom: 16px; }
    .feature h3 { font-size: 1.15rem; font-weight: 800; margin-bottom: 6px; }
    .feature p { margin: 0; color: var(--muted); }

    .split { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; }
    .split h2 { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; margin: 14px 0 12px; }
    .split p.lead { color: var(--muted); font-size: 1.05rem; margin: 0 0 22px; }
    .checks { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
    .checks li { display: flex; gap: 10px; align-items: flex-start; }
    .checks .icon { color: #fff; background: var(--brand); border-radius: 50%; padding: 3px; width: 22px; height: 22px; margin-top: 3px; }
    .paper { border-radius: 18px; overflow: hidden; border: 1px solid var(--line); box-shadow: 0 30px 60px rgb(15 23 42 / .14); max-height: 560px; background: #fff; }
    .paper img { width: 100%; object-fit: cover; object-position: top; }

    .portals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .portal { border-radius: var(--radius); padding: 28px; color: #fff; position: relative; overflow: hidden; min-height: 220px; }
    .portal:nth-child(1) { background: linear-gradient(140deg, #0f766e, #134e4a); }
    .portal:nth-child(2) { background: linear-gradient(140deg, #1e40af, #1e3a8a); }
    .portal:nth-child(3) { background: linear-gradient(140deg, #c2410c, #7c2d12); }
    .portal::after { content: ""; position: absolute; width: 180px; height: 180px; border-radius: 50%; background: rgb(255 255 255 / .08); inset-inline-end: -50px; bottom: -70px; }
    .portal .ico { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: rgb(255 255 255 / .15); margin-bottom: 16px; }
    .portal h3 { color: #fff; font-size: 1.2rem; font-weight: 800; margin-bottom: 8px; }
    .portal p { margin: 0; opacity: .88; }

    .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; counter-reset: step; }
    .step { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 26px; position: relative; }
    .step::before { counter-increment: step; content: counter(step); width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center;
        background: var(--ink); color: #fff; font-weight: 800; margin-bottom: 14px; }
    .step h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 6px; }
    .step p { margin: 0; color: var(--muted); }

    .toggle { display: inline-flex; background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: 4px; margin: 0 auto 36px; }
    .toggle button { font: inherit; font-weight: 700; border: 0; background: transparent; padding: 8px 20px; border-radius: 999px; cursor: pointer; color: var(--muted); }
    .toggle button.on { background: var(--ink); color: #fff; }
    .toggle .save { font-size: .75rem; background: #dcfce7; color: #166534; padding: 1px 8px; border-radius: 999px; margin-inline-start: 6px; }
    .plans { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; align-items: stretch; }
    .plan { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 30px; display: flex; flex-direction: column; gap: 18px; position: relative; }
    .plan.popular { border: 2px solid var(--brand); box-shadow: 0 24px 60px rgb(15 118 110 / .18); }
    .plan .tag { position: absolute; top: -13px; inset-inline-start: 50%; transform: translateX(50%); background: var(--brand); color: #fff; font-size: .8rem; font-weight: 700; padding: 3px 14px; border-radius: 999px; white-space: nowrap; }
    [dir="ltr"] .plan .tag { transform: translateX(-50%); }
    .plan h3 { font-size: 1.25rem; font-weight: 800; }
    .plan .amount { display: flex; align-items: baseline; gap: 6px; }
    .plan .amount b { font-size: 2.6rem; font-weight: 800; color: var(--ink); line-height: 1; }
    .plan .amount span { color: var(--muted); }
    .plan .limits { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .plan .limits div { background: var(--soft); border-radius: 10px; padding: 8px 10px; font-size: .88rem; }
    .plan .limits b { display: block; color: var(--ink); }
    .plan ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 9px; font-size: .95rem; }
    .plan li { display: flex; gap: 8px; align-items: center; }
    .plan li .icon { width: 18px; height: 18px; color: var(--brand); }
    .plan li.off { color: #94a3b8; text-decoration: line-through; }
    .plan li.off .icon { color: #cbd5e1; }
    .plan .btn { margin-top: auto; width: 100%; }
    .yearly { display: none; } .show-yearly .yearly { display: inline; } .show-yearly .monthly { display: none; }

    .faq { max-width: 820px; margin: 0 auto; display: grid; gap: 12px; }
    .faq details { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px 22px; }
    .faq summary { font-weight: 800; color: var(--ink); cursor: pointer; list-style: none; display: flex; justify-content: space-between; gap: 12px; }
    .faq summary::-webkit-details-marker { display: none; }
    .faq summary::after { content: "+"; font-size: 1.4rem; line-height: 1; color: var(--brand); }
    .faq details[open] summary::after { content: "−"; }
    .faq p { margin: 12px 0 0; color: var(--muted); }

    .final { padding: 0 0 88px; }
    .final .box { border-radius: 24px; padding: 56px 32px; text-align: center; color: #fff;
        background: radial-gradient(600px 240px at 20% 0%, rgb(255 255 255 / .14), transparent 60%), linear-gradient(135deg, #0f766e, #134e4a); }
    .final h2 { color: #fff; font-size: clamp(1.6rem, 3vw, 2.3rem); font-weight: 800; margin-bottom: 10px; }
    .final p { opacity: .88; margin: 0 0 24px; font-size: 1.08rem; }

    @media (max-width: 960px) {
        .features, .portals, .steps, .plans { grid-template-columns: 1fr 1fr; }
        .split { grid-template-columns: 1fr; }
        .stats { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 640px) {
        .features, .portals, .steps, .plans { grid-template-columns: 1fr; }
        .browser { margin-top: 32px; }
    }
@endsection

@section('content')
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <span class="eyebrow">{{ __('نظام إدارة أملاك سحابي للمنشآت العقارية في المملكة') }}</span>
                <h1>{{ __('أدر عقاراتك وعقودك وتحصيلك') }} <span class="hl">{{ __('من مكان واحد') }}</span></h1>
                <p class="lead">{{ __('عقار نوفا يجمع الوحدات والعقود والدفعات وحسابات الملاك والفوترة الإلكترونية في نظام واحد سهل، مع بوابة للملاك وأخرى للمستأجرين وموقع خاص لمؤسستك.') }}</p>
                <div class="cta">
                    <a class="btn btn-primary btn-lg" href="{{ route('central.signup', [], false) }}">{{ __('ابدأ تجربتك المجانية') }}</a>
                    @if ($demoUrl)
                        <a class="btn btn-ghost btn-lg" href="#demo">{{ __('جرّب النسخة التجريبية') }}</a>
                    @else
                        <a class="btn btn-ghost btn-lg" href="#pricing">{{ __('شاهد الأسعار') }}</a>
                    @endif
                </div>
                <div class="note">
                    <span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['check'] }}"/></svg>{{ __('14 يومًا مجانًا') }}</span>
                    <span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['check'] }}"/></svg>{{ __('بدون بطاقة ائتمان') }}</span>
                    <span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['check'] }}"/></svg>{{ __('ننقل بياناتك من نظام رواد السابق') }}</span>
                </div>
            </div>
            <div class="browser">
                <div class="chrome"><i></i><i></i><i></i><span class="url">your-company.{{ config('rowad.tenancy.base_domain') }}/admin</span></div>
                <img src="{{ asset('images/platform/dashboard.jpg') }}" width="1440" height="900" alt="{{ __('لوحة تحكم المؤسسة في عقار نوفا') }}">
            </div>
        </div>
    </section>

    <section style="padding:44px 0" class="alt">
        <div class="container stats">
            <div class="stat"><b>{{ __('هجري / ميلادي') }}</b><span>{{ __('عقود ودفعات بالتقويمين') }}</span></div>
            <div class="stat"><b>{{ __('عربي أولًا') }}</b><span>{{ __('واجهة ومطبوعات عربية مع الإنجليزية') }}</span></div>
            <div class="stat"><b>{{ __('زاتكا') }}</b><span>{{ __('فواتير ضريبية برمز QR وضريبة 15%') }}</span></div>
            <div class="stat"><b>{{ __('قاعدة مستقلة') }}</b><span>{{ __('بيانات كل مؤسسة منفصلة تمامًا') }}</span></div>
        </div>
    </section>

    <section id="features">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">{{ __('المزايا') }}</span>
                <h2>{{ __('كل ما تحتاجه مؤسستك العقارية') }}</h2>
                <p>{{ __('من إضافة الوحدة إلى تسوية حساب المالك، كل خطوة مؤتمتة وموثقة.') }}</p>
            </div>
            <div class="features">
                @foreach ($features as [$icon, $title, $text])
                    <div class="feature">
                        <div class="ico"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$icon] }}"/></svg></div>
                        <h3>{{ __($title) }}</h3>
                        <p>{{ __($text) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="alt">
        <div class="container split">
            <div>
                <span class="eyebrow">{{ __('المطبوعات') }}</span>
                <h2>{{ __('عقود وسندات تليق بمؤسستك') }}</h2>
                <p class="lead">{{ __('كل مستند يخرج بهوية مؤسستك وبتصميم حديث، ويحمل كل البيانات النظامية المطلوبة.') }}</p>
                <ul class="checks">
                    @foreach (['بيانات الأطراف كاملة بالهوية والجنسية والجوال', 'جدول الدفعات وحالة كل دفعة، والمبالغ كتابةً', 'رمز QR للتحقق من صحة المستند', 'محرر قوالب لتعديل أي نموذج مع حفظ النسخ السابقة', 'حفظ PDF أو طباعة مباشرة'] as $line)
                        <li><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['check'] }}"/></svg>{{ __($line) }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="paper"><img src="{{ asset('images/platform/contract.jpg') }}" alt="{{ __('نموذج عقد إيجار مطبوع من عقار نوفا') }}" loading="lazy"></div>
        </div>
    </section>

    <section id="ai" class="ai-sec">
        <div class="container split">
            <div>
                <span class="eyebrow">{{ __('الذكاء الاصطناعي') }}</span>
                <h2>{{ __('اسأل نظامك… ويجيبك بالأرقام') }}</h2>
                <p class="lead">{{ __('مساعد ذكي داخل النظام يقرأ بيانات مؤسستك ويحللها: اسأله بالعربية عن أي شيء، واحصل على إجابة دقيقة وتوصيات عملية.') }}</p>
                <ul class="checks">
                    @foreach (['اسأل عن المتأخرات والعقود القريبة من الانتهاء والإشغال والأرباح', 'تحليل ذكي لأي تقرير بضغطة زر، ورؤى يومية في لوحة التحكم', 'يكتب لك الرسائل الجماعية والخطابات بالأسلوب الذي تختاره', 'للملاك والمستأجرين أيضًا: كل منهم يسأل عن بياناته فقط', 'يقرأ ويحلل فقط ولا يعدّل شيئًا، ويحترم صلاحيات كل مستخدم'] as $line)
                        <li><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['check'] }}"/></svg>{{ __($line) }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="ai-mock" aria-hidden="true">
                <div class="ai-mock__q">{{ __('من المستأجرون المتأخرون هذا الشهر؟') }}</div>
                <div class="ai-mock__a">
                    <span class="ai-mock__mark">✦</span>
                    <div>
                        <p>{!! __('يوجد <b>4 أقساط متأخرة</b> بإجمالي <b>15,000 ر.س</b>، أقدمها منذ 14 يومًا:') !!}</p>
                        <table>
                            <tr><th>{{ __('الوحدة') }}</th><th>{{ __('المستأجر') }}</th><th>{{ __('المتبقي') }}</th></tr>
                            <tr><td>{{ __('شقة 204') }}</td><td>{{ __('ماجد الغامدي') }}</td><td>2,833</td></tr>
                            <tr><td>{{ __('محل تجاري 3') }}</td><td>{{ __('شركة الأفق') }}</td><td>5,417</td></tr>
                        </table>
                        <p class="ai-mock__tip">{{ __('أنصح بإرسال تذكير جماعي الآن، ثم متابعة من تجاوز 30 يومًا هاتفيًا.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="portals">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">{{ __('البوابات') }}</span>
                <h2>{{ __('الكل يرى ما يخصه، في أي وقت') }}</h2>
                <p>{{ __('أقل اتصالات واستفسارات: المالك والمستأجر يجدان بياناتهما بأنفسهم.') }}</p>
            </div>
            <div class="portals">
                <div class="portal">
                    <div class="ico"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['scale'] }}"/></svg></div>
                    <h3>{{ __('بوابة الملاك') }}</h3>
                    <p>{{ __('كشف الحساب والمستحقات، عقود وحداته، تقارير الحالة، وتذاكر الدعم.') }}</p>
                </div>
                <div class="portal">
                    <div class="ico"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['users'] }}"/></svg></div>
                    <h3>{{ __('بوابة المستأجرين') }}</h3>
                    <p>{{ __('العقد والدفعات القادمة والسندات والفواتير، وطلبات الصيانة.') }}</p>
                </div>
                <div class="portal">
                    <div class="ico"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['phone'] }}"/></svg></div>
                    <h3>{{ __('تطبيق الجوال') }}</h3>
                    <p>{{ __('واجهة API آمنة لتطبيقات المؤسسة والملاك والمستأجرين على الجوال.') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="alt">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">{{ __('البداية') }}</span>
                <h2>{{ __('جاهز خلال دقائق') }}</h2>
            </div>
            <div class="steps">
                <div class="step"><h3>{{ __('أنشئ حساب مؤسستك') }}</h3><p>{{ __('اختر الباقة ورابط مؤسستك، ويُنشأ نظامك وقاعدة بياناتك المستقلة فورًا.') }}</p></div>
                <div class="step"><h3>{{ __('انقل بياناتك') }}</h3><p>{{ __('أضف وحداتك وعقودك، وإن كنت على نظام رواد السابق ننقلها كما هي بأرقامها وتواريخها.') }}</p></div>
                <div class="step"><h3>{{ __('ابدأ التحصيل') }}</h3><p>{{ __('الدفعات تُجدول تلقائيًا، والتنبيهات تصل للمستأجرين، والملاك يرون حساباتهم.') }}</p></div>
            </div>
        </div>
    </section>

    @if ($demoUrl)
        <section id="demo" class="alt">
            <div class="container">
                <div class="section-head">
                    <span class="eyebrow">{{ __('النسخة التجريبية') }}</span>
                    <h2>{{ __('جرّب النظام الآن بدون تسجيل') }}</h2>
                    <p>{{ __('مؤسسة تجريبية ببيانات حقيقية الشكل. ادخل بضغطة واحدة كموظف أو مالك أو مستأجر. تُعاد البيانات كل يوم ولا تُرسل أي رسائل حقيقية.') }}</p>
                </div>
                <div class="portals demo-cards">
                    @foreach ([
                        ['admin', 'building', 'لوحة المؤسسة', 'الوحدات والعقود والتحصيل والتقارير والمساعد الذكي كما يراها مدير المؤسسة.', 'admin@demo.test'],
                        ['owner', 'scale', 'بوابة المالك', 'كشف حساب المالك ووحداته والتحصيلات القادمة.', 'owner@demo.test'],
                        ['client', 'users', 'بوابة المستأجر', 'العقد والدفعات القادمة والسندات وطلبات الدعم.', 'client@demo.test'],
                    ] as [$role, $icon, $title, $text, $email])
                        <div class="portal">
                            <div class="ico"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$icon] }}"/></svg></div>
                            <h3>{{ __($title) }}</h3>
                            <p>{{ __($text) }}</p>
                            <a class="btn btn-light demo-btn" href="{{ $demoUrl }}/demo/{{ $role }}" target="_blank" rel="noopener">{{ __('ادخل الآن') }}</a>
                            <small class="demo-cred" dir="ltr">{{ $email }} · {{ \App\Console\Commands\CreateDemoOffice::PASSWORD }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section id="pricing">
        <div class="container" style="text-align:center">
            <div class="section-head">
                <span class="eyebrow">{{ __('الأسعار') }}</span>
                <h2>{{ __('باقات واضحة تناسب حجم مؤسستك') }}</h2>
                <p>{{ __('كل الباقات تبدأ بتجربة مجانية كاملة. الأسعار بالريال السعودي.') }}</p>
            </div>
            @if ($plans->isNotEmpty())
                @php $maxSaving = $plans->map(fn ($p) => $p->monthly_price > 0 ? round(100 - $p->yearly_price / ($p->monthly_price * 12) * 100) : 0)->max(); @endphp
                <div class="toggle" role="group">
                    <button type="button" class="on" data-cycle="monthly">{{ __('شهري') }}</button>
                    <button type="button" data-cycle="yearly">{{ __('سنوي') }} @if ($maxSaving > 0)<span class="save">{{ __('وفّر حتى :p%', ['p' => $maxSaving]) }}</span>@endif</button>
                </div>
                <div class="plans" id="plans" style="text-align:start">
                    @foreach ($plans as $plan)
                        @php $popular = $plan->slug === 'professional'; @endphp
                        <div class="plan {{ $popular ? 'popular' : '' }}">
                            @if ($popular)<span class="tag">{{ __('الأكثر اختيارًا') }}</span>@endif
                            <h3>{{ app()->getLocale() === 'ar' ? $plan->name_ar : $plan->name_en }}</h3>
                            <div class="amount">
                                <b class="monthly">{{ number_format($plan->monthly_price) }}</b><span class="monthly">{{ __('ر.س / شهريًا') }}</span>
                                <b class="yearly">{{ number_format($plan->yearly_price) }}</b><span class="yearly">{{ __('ر.س / سنويًا') }}</span>
                            </div>
                            <div class="limits">
                                <div><b>{{ $limit($plan->max_units, 'وحدة') }}</b>{{ __('الوحدات') }}</div>
                                <div><b>{{ $limit($plan->max_users, 'مستخدم') }}</b>{{ __('المستخدمون') }}</div>
                                <div><b>{{ $limit($plan->max_sms_per_month, 'رسالة') }}</b>{{ __('SMS شهريًا') }}</div>
                                <div><b>{{ $plan->max_storage_mb === null ? __('غير محدود') : ($plan->max_storage_mb >= 1024 ? round($plan->max_storage_mb / 1024).' '.__('جيجا') : $plan->max_storage_mb.' '.__('ميجا')) }}</b>{{ __('التخزين') }}</div>
                            </div>
                            <ul>
                                @foreach ($allFeatures as $feature)
                                    <li class="{{ $plan->hasFeature($feature) ? '' : 'off' }}"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['check'] }}"/></svg>{{ __($featureLabels[$feature]) }}</li>
                                @endforeach
                            </ul>
                            <a class="btn {{ $popular ? 'btn-primary' : 'btn-ghost' }}" href="{{ route('central.signup', ['plan' => $plan->id], false) }}">{{ __('ابدأ مجانًا :days يومًا', ['days' => $plan->trial_days]) }}</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="faq" class="alt">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow">{{ __('الأسئلة الشائعة') }}</span>
                <h2>{{ __('أسئلة تصلنا كثيرًا') }}</h2>
            </div>
            <div class="faq">
                @foreach ([
                    ['أستخدم نظام رواد السابق، هل تنتقل بياناتي؟', 'نعم. ننقل الوحدات والملاك والمستأجرين والعقود والدفعات والسندات وحسابات الملاك ونماذج الطباعة مع الحفاظ على الأرقام والتواريخ، وتراجعها قبل الاعتماد.'],
                    ['هل بيانات مؤسستي منفصلة عن المؤسسات الأخرى؟', 'كل مؤسسة لها قاعدة بيانات مستقلة تمامًا ومجلد ملفات خاص، ويمكنك تنزيل نسخة احتياطية كاملة من الإعدادات في أي وقت.'],
                    ['هل يدعم التقويم الهجري؟', 'نعم، يمكن إنشاء العقود وجدولة الدفعات بالتقويم الهجري (أم القرى) أو الميلادي، وتظهر التواريخ بالتقويمين.'],
                    ['ماذا يحدث بعد انتهاء الفترة التجريبية؟', 'تختار الباقة المناسبة وتكمل بنفس بياناتك. لا يُحذف شيء، ولا نطلب بطاقة ائتمان عند التسجيل.'],
                    ['هل يمكنني استخدام رابط باسم مؤسستي؟', 'كل مؤسسة تحصل على رابط فرعي مجاني، وباقة المؤسسات تتيح ربط نطاقك الخاص.'],
                    ['هل يطلع الذكاء الاصطناعي على كل بيانات مؤسستي؟', 'لا. المساعد يقرأ فقط البيانات اللازمة لكل سؤال، وبصلاحيات المستخدم الذي يسأل، ولا يعدّل أو يرسل شيئًا. والمالك والمستأجر يسألان عن بياناتهما فقط.'],
                ] as [$q, $a])
                    <details @if ($loop->first) open @endif><summary>{{ __($q) }}</summary><p>{{ __($a) }}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="final" style="padding-top:88px">
        <div class="container">
            <div class="box">
                <h2>{{ __('جاهز تنقل مؤسستك لمستوى أعلى؟') }}</h2>
                <p>{{ __('أنشئ حساب مؤسستك الآن وابدأ تجربتك المجانية خلال دقيقتين.') }}</p>
                <a class="btn btn-light btn-lg" href="{{ route('central.signup', [], false) }}">{{ __('ابدأ تجربتك المجانية') }}</a>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script>
        document.querySelectorAll('.toggle button').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('.toggle button').forEach(function (b) { b.classList.toggle('on', b === button); });
                document.getElementById('plans').classList.toggle('show-yearly', button.dataset.cycle === 'yearly');
            });
        });
    </script>
@endsection
