<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800&display=swap" rel="stylesheet">
    <style>
        /* Rowad document design system — the classes the default print templates use (resources/print-templates). */
        :root { --brand: #0f766e; --brand-soft: #f0fdfa; --brand-line: #ccfbf1; --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --soft: #f8fafc; }
        @page { size: A4; margin: 12mm 12mm 14mm; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: 'Cairo', system-ui, sans-serif; color: var(--ink); font-size: 12.5px; line-height: 1.75; margin: 0; background: #eef1f4; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 14mm 14mm 16mm; background: #fff; box-shadow: 0 10px 40px rgb(15 23 42 / .12); position: relative; }
        .sheet::before { content: ""; position: absolute; inset-inline: 0; top: 0; height: 6px; background: linear-gradient(90deg, #0f766e, #14b8a6 60%, #99f6e4); }
        img[src=""], img:not([src]) { display: none; }
        p { margin: 6px 0; }
        b, strong { font-weight: 700; }

        /* header & footer */
        .doc-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--line); }
        .doc-brand { display: flex; align-items: center; gap: 12px; }
        .doc-logo { height: 54px; width: auto; }
        .doc-org { font-size: 18px; font-weight: 800; color: var(--brand); line-height: 1.3; }
        .doc-org-en { font-size: 11px; color: var(--muted); letter-spacing: .04em; direction: ltr; text-align: start; }
        .doc-contact { display: flex; flex-direction: column; align-items: flex-end; font-size: 11px; color: var(--muted); line-height: 1.6; }
        .doc-footer { display: flex; justify-content: space-between; gap: 12px; margin-top: 22px; padding-top: 10px; border-top: 1px solid var(--line); font-size: 10.5px; color: var(--muted); }

        /* title block */
        .doc-title { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin: 18px 0 14px; }
        .doc-kicker { font-size: 10px; font-weight: 700; letter-spacing: .18em; color: var(--brand); direction: ltr; text-align: start; }
        .doc-title h1 { margin: 0; font-size: 26px; font-weight: 800; line-height: 1.3; }
        .doc-ids { display: flex; gap: 8px; }
        .doc-ids > div { background: var(--soft); border: 1px solid var(--line); border-radius: 10px; padding: 6px 12px; min-width: 110px; }
        .doc-ids span { display: block; font-size: 10px; color: var(--muted); }
        .doc-ids b { font-size: 13px; direction: ltr; unicode-bidi: plaintext; }
        .doc-lead { color: #334155; margin: 0 0 12px; }

        /* sections */
        .section { margin: 12px 0; break-inside: avoid; }
        .section h2 { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; margin: 0 0 8px; color: var(--ink); }
        .section h2::before { content: ""; width: 4px; height: 16px; border-radius: 4px; background: var(--brand); }
        /* sections with nothing to show (no deeds, meters, terms...) disappear */
        .section:has(tbody:empty), .section:has(> h2:only-child) { display: none; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* key/value grid */
        .kv { display: grid; grid-template-columns: repeat(4, 1fr); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin: 0; }
        .kv > div { padding: 7px 10px; border-bottom: 1px solid var(--line); border-inline-start: 1px solid var(--line); }
        .kv > div:nth-child(4n + 1) { border-inline-start: 0; }
        .kv > div.wide { grid-column: span 2; }
        .kv > div.full { grid-column: 1 / -1; border-inline-start: 0; }
        .kv dt { font-size: 10px; color: var(--muted); margin: 0; }
        .kv dd { margin: 0; font-weight: 700; min-height: 1.5em; }
        .kv.cols-3 { grid-template-columns: repeat(3, 1fr); }
        .kv.cols-3 > div:nth-child(4n + 1) { border-inline-start: 1px solid var(--line); }
        .kv.cols-3 > div:nth-child(3n + 1) { border-inline-start: 0; }

        /* tables */
        table.data, table.grid { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin: 0; }
        table.data th, table.grid th { background: var(--soft); color: #475569; font-size: 10.5px; font-weight: 700; text-align: start; padding: 7px 10px; border-bottom: 1px solid var(--line); }
        table.data td, table.grid td { padding: 7px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        table.data tr:last-child td, table.grid tr:last-child td { border-bottom: 0; }
        table.data tbody tr:nth-child(even) td, table.grid tbody tr:nth-child(even) td { background: #fcfdfd; }
        table.data td p, table.grid td p { margin: 0; text-align: start !important; }
        .num { direction: ltr; unicode-bidi: plaintext; text-align: end; white-space: nowrap; }

        /* money */
        .totals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .total-card { border: 1px solid var(--line); border-radius: 12px; padding: 8px 12px; }
        .total-card span { display: block; font-size: 10px; color: var(--muted); }
        .total-card b { font-size: 15px; direction: ltr; unicode-bidi: plaintext; }
        .total-card.accent { background: var(--brand); border-color: var(--brand); color: #fff; }
        .total-card.accent span { color: rgb(255 255 255 / .8); }
        .amount-words { margin-top: 8px; padding: 8px 12px; border-radius: 10px; background: var(--brand-soft); border: 1px dashed #99f6e4; color: #134e4a; font-weight: 600; }
        .hero-amount { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 14px 18px; border-radius: 14px; background: var(--brand); color: #fff; margin: 4px 0 12px; }
        .hero-amount span { font-size: 11px; opacity: .85; display: block; }
        .hero-amount b { font-size: 26px; font-weight: 800; direction: ltr; unicode-bidi: plaintext; }

        /* terms, letters, signatures */
        .terms ol, ol.terms { margin: 0; padding-inline-start: 20px; }
        .terms li, ol.terms li { margin: 3px 0; }
        .letter-body { font-size: 13.5px; line-height: 2; min-height: 90mm; }
        .letter-to { font-weight: 800; font-size: 14px; }
        .signatures { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: end; margin-top: 20px; break-inside: avoid; }
        .signatures.three { grid-template-columns: repeat(3, 1fr); }
        .sign { border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; min-height: 92px; }
        .sign b { display: block; font-size: 11.5px; margin-bottom: 26px; }
        .sign span { display: block; border-top: 1px dashed #cbd5e1; padding-top: 4px; font-size: 10px; color: var(--muted); }
        .qr { text-align: center; font-size: 9.5px; color: var(--muted); }
        .qr img { width: 92px; height: 92px; display: block; margin: 0 auto 2px; }
        .badge { display: inline-block; padding: 1px 10px; border-radius: 999px; background: var(--brand-soft); color: var(--brand); font-weight: 700; font-size: 11px; }
        .muted { color: var(--muted); }

        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; justify-content: center; gap: 8px; background: rgb(255 255 255 / .9); backdrop-filter: blur(6px); border-bottom: 1px solid var(--line); padding: 8px; }
        .toolbar button { font: inherit; font-weight: 700; padding: 6px 18px; border-radius: 10px; border: 1px solid var(--brand); background: var(--brand); color: #fff; cursor: pointer; }
        .pagebreak { break-before: page; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .sheet::before { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">{{ __('طباعة / حفظ PDF') }}</button></div>
    <div class="sheet">{!! $html !!}</div>
</body>
</html>
