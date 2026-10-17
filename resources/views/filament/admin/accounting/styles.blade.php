{{-- Shared look of the accounting reports and entry lines: a ledger-style table, a period bar, print layout. --}}
<style>
    .rowad-acc-bar { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; padding: 14px 18px; background: var(--rowad-card, #fff); border: 1px solid var(--rowad-line, #e4e9ec); border-radius: 16px; box-shadow: var(--rowad-shadow); }
    .rowad-acc-bar label { display: grid; gap: 4px; font-size: .78rem; font-weight: 700; color: var(--rowad-muted, #64748b); }
    .rowad-acc-bar input, .rowad-acc-bar select { font: inherit; font-size: .88rem; color: var(--rowad-ink, #0f172a); background: #fff; border: 1px solid var(--rowad-line, #e4e9ec); border-radius: 10px; padding: 7px 10px; min-width: 150px; }
    .rowad-acc-bar select { min-width: 280px; }
    .rowad-acc-bar .check { display: flex; align-items: center; gap: 8px; font-size: .86rem; color: var(--rowad-ink); padding-bottom: 8px; }
    .rowad-acc-bar .check input { min-width: 0; }
    .rowad-acc-presets { display: flex; gap: 6px; padding-bottom: 4px; }
    .rowad-acc-presets button { font: inherit; font-size: .78rem; font-weight: 700; padding: 6px 10px; border-radius: 999px; border: 1px solid var(--rowad-line); background: #f8fafc; color: #475569; cursor: pointer; }
    .rowad-acc-presets button:hover { background: var(--rowad-brand-soft); color: var(--rowad-brand-dark); border-color: var(--rowad-brand-line); }
    .rowad-acc-card { background: var(--rowad-card, #fff); border: 1px solid var(--rowad-line, #e4e9ec); border-radius: 16px; box-shadow: var(--rowad-shadow); overflow: hidden; }
    .rowad-acc-card h3 { margin: 0; padding: 14px 18px; font-size: 1rem; font-weight: 800; color: var(--rowad-ink); border-bottom: 1px solid var(--rowad-line); background: linear-gradient(180deg, #fbfcfc, #f6f9f9); display: flex; justify-content: space-between; gap: 12px; }
    .rowad-acc-card h3 small { font-size: .78rem; font-weight: 600; color: var(--rowad-muted); }
    .rowad-acc-table-wrap { overflow-x: auto; }
    .rowad-acc-table { width: 100%; border-collapse: collapse; font-size: .86rem; font-variant-numeric: tabular-nums; }
    .rowad-acc-table th { text-align: start; font-size: .74rem; font-weight: 800; color: var(--rowad-muted); padding: 9px 12px; background: #f8fafc; border-bottom: 1px solid var(--rowad-line); white-space: nowrap; }
    .rowad-acc-table td { padding: 8px 12px; border-bottom: 1px solid #eef2f4; color: #1e293b; vertical-align: top; }
    .rowad-acc-table .num { text-align: end; white-space: nowrap; direction: ltr; unicode-bidi: isolate; }
    .rowad-acc-table th.num { text-align: end; }
    .rowad-acc-table .code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #475569; white-space: nowrap; }
    .rowad-acc-table .muted, .rowad-acc-table small { color: var(--rowad-muted); }
    .rowad-acc-table a { color: var(--rowad-brand); font-weight: 600; }
    .rowad-acc-table tr.is-group td { font-weight: 800; background: #fbfcfd; }
    .rowad-acc-table tr.is-top td { background: #f3f7f8; }
    .rowad-acc-table tfoot td, .rowad-acc-table tr.is-total td { font-weight: 800; background: #f1f5f9; border-top: 2px solid #cbd5e1; }
    .rowad-acc-table tr.is-grand td { font-size: .95rem; background: var(--rowad-brand-soft); color: var(--rowad-brand-dark); }
    .rowad-acc-table .neg { color: #b91c1c; }
    .rowad-acc-ok, .rowad-acc-warn { display: inline-flex; align-items: center; gap: 6px; font-size: .8rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
    .rowad-acc-ok { background: #ecfdf5; color: #047857; } .rowad-acc-warn { background: #fef2f2; color: #b91c1c; }
    .rowad-acc-grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); align-items: start; }
    .rowad-acc-tabs { display: inline-flex; gap: 4px; padding: 4px; border-radius: 12px; background: #eef2f4; }
    .rowad-acc-tabs button { font: inherit; font-size: .86rem; font-weight: 700; padding: 7px 16px; border: 0; border-radius: 9px; background: transparent; color: #475569; cursor: pointer; }
    .rowad-acc-tabs button.is-on { background: #fff; color: var(--rowad-ink); box-shadow: 0 1px 3px rgb(15 23 42 / .1); }
    .rowad-acc-empty { padding: 36px 18px; text-align: center; color: var(--rowad-muted); }
    .rowad-acc-print-head { display: none; }
    @media print {
        .fi-sidebar, .fi-topbar-ctn, .rowad-acc-bar, .fi-header-actions-ctn, .rowad-demo-banner, .rowad-credit, .fi-header { display: none !important; }
        .fi-main-ctn, .fi-main { padding: 0 !important; margin: 0 !important; }
        .rowad-acc-print-head { display: block; margin-bottom: 12px; }
        .rowad-acc-print-head h1 { margin: 0; font-size: 1.2rem; } .rowad-acc-print-head p { margin: 2px 0 0; color: #475569; font-size: .85rem; }
        .rowad-acc-card { box-shadow: none; break-inside: avoid; }
        body { background: #fff !important; }
    }
</style>
