{{-- Aqar Nova panel theme on top of Filament: branded sidebar, layered cards, readable tables, Cairo throughout. --}}
<style>
    :root {
        --rowad-surface: #f3f6f7; --rowad-card: #ffffff; --rowad-line: #e4e9ec; --rowad-ink: #0f172a; --rowad-muted: #64748b;
        --rowad-brand: #0f766e; --rowad-brand-dark: #0b5d57; --rowad-brand-soft: #e6f4f2; --rowad-accent: #eb6834;
        --rowad-side: #0b2a2e; --rowad-side-2: #0e3a3f; --rowad-side-ink: #d7e6e4; --rowad-side-muted: #7fa3a0;
        --rowad-shadow: 0 1px 2px rgb(15 23 42 / .04), 0 6px 20px rgb(15 23 42 / .06);
        --rowad-radius: 18px;
    }
    body.fi-body, .fi-layout, .fi-main-ctn { background: var(--rowad-surface); }
    body, .fi-body { font-feature-settings: "tnum" 1; }

    /* ---------- sidebar: deep teal, white content ---------- */
    .fi-sidebar { background: linear-gradient(180deg, var(--rowad-side) 0%, var(--rowad-side-2) 100%) !important; border-inline-end: 0 !important; box-shadow: 4px 0 24px rgb(2 20 22 / .18); }
    .fi-sidebar-header { background: transparent !important; border-bottom: 1px solid rgb(255 255 255 / .08) !important; box-shadow: none !important; }
    .fi-sidebar-header .fi-logo, .fi-sidebar-header .fi-logo * { color: #fff !important; }
    .fi-sidebar-header img.fi-logo { filter: brightness(1.15); }
    .fi-sidebar-nav { padding: 14px 12px 24px !important; }
    .fi-sidebar-group-label { font-size: .7rem; letter-spacing: .06em; text-transform: uppercase; color: var(--rowad-side-muted) !important; font-weight: 700; padding-inline-start: 10px; }
    .fi-sidebar-group-btn .fi-icon, .fi-sidebar-group-collapse-btn .fi-icon, .fi-sidebar-group-collapse-btn svg { color: var(--rowad-side-muted) !important; }
    .fi-sidebar-item-btn { border-radius: 12px !important; padding: 9px 12px !important; transition: background .15s ease, color .15s ease; }
    .fi-sidebar-item-btn:hover { background: rgb(255 255 255 / .07) !important; }
    .fi-sidebar-item-label { color: var(--rowad-side-ink) !important; font-weight: 500; font-size: .92rem; }
    .fi-sidebar-item-btn .fi-icon, .fi-sidebar-item-btn > svg { color: var(--rowad-side-muted) !important; width: 1.25rem; height: 1.25rem; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn, .fi-sidebar-item-btn.fi-active { background: linear-gradient(135deg, rgb(20 184 166 / .28), rgb(15 118 110 / .22)) !important; box-shadow: inset 0 0 0 1px rgb(94 234 212 / .25); }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label, .fi-sidebar-item-btn.fi-active .fi-sidebar-item-label { color: #fff !important; font-weight: 700; }
    .fi-sidebar-item.fi-active .fi-icon, .fi-sidebar-item-btn.fi-active .fi-icon, .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > svg { color: #5eead4 !important; }
    .fi-sidebar-item-grouped-border-part { border-color: rgb(255 255 255 / .12) !important; }
    .fi-sidebar-item-badge-ctn .fi-badge { background: var(--rowad-accent) !important; color: #fff !important; border: 0 !important; }
    .fi-sidebar-database-notifications-btn-label { color: var(--rowad-side-ink) !important; }
    .fi-sidebar::-webkit-scrollbar, .fi-sidebar-nav::-webkit-scrollbar { width: 6px; }
    .fi-sidebar-nav::-webkit-scrollbar-thumb { background: rgb(255 255 255 / .12); border-radius: 6px; }

    /* ---------- top bar & page header ---------- */
    .fi-topbar { background: rgb(255 255 255 / .92) !important; backdrop-filter: blur(10px); box-shadow: 0 1px 0 var(--rowad-line), 0 6px 18px rgb(15 23 42 / .03) !important; }
    .fi-topbar .fi-logo, .fi-topbar .fi-logo * { color: var(--rowad-ink); }
    .fi-header-heading { font-weight: 800; letter-spacing: -.01em; font-size: 1.55rem; }
    .fi-header-subheading { color: var(--rowad-muted); }
    .fi-breadcrumbs-item-label { color: var(--rowad-muted); font-size: .82rem; }
    .fi-header { padding-bottom: 6px; }

    /* ---------- cards, sections ---------- */
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat, .fi-fo-tabs, .fi-sc-tabs, .fi-tabs {
        border-radius: var(--rowad-radius) !important; border: 1px solid var(--rowad-line) !important; box-shadow: var(--rowad-shadow) !important; background: var(--rowad-card);
    }
    .fi-section-header { padding: 14px 20px !important; border-bottom: 1px solid var(--rowad-line); background: linear-gradient(180deg, #fbfcfc, #f6f9f9); border-radius: var(--rowad-radius) var(--rowad-radius) 0 0; }
    .fi-section-header-heading { font-weight: 800; font-size: 1rem; color: var(--rowad-ink); }
    .fi-section-header-description { color: var(--rowad-muted); font-size: .85rem; }
    .fi-section-header .fi-icon { color: var(--rowad-brand) !important; background: var(--rowad-brand-soft); border-radius: 10px; padding: 6px; width: 34px; height: 34px; }
    .fi-section-content-ctn { padding: 18px 20px !important; }
    .fi-section:not(:has(.fi-section-header)) .fi-section-content-ctn { padding-top: 20px !important; }

    /* ---------- infolist entries: quiet label, strong value ---------- */
    .fi-in-entry-label { font-size: .76rem !important; font-weight: 600 !important; color: var(--rowad-muted) !important; letter-spacing: .01em; }
    .fi-in-entry-content { font-size: .95rem; color: var(--rowad-ink); }
    .fi-in-entry { padding: 2px 0; }

    /* ---------- forms ---------- */
    .fi-fo-field-label { font-weight: 600 !important; color: #334155 !important; }
    .fi-input-wrp, .fi-select-input { border-radius: 10px !important; }
    .fi-input-wrp:focus-within { box-shadow: 0 0 0 3px rgb(15 118 110 / .14) !important; }

    /* ---------- tables ---------- */
    .fi-ta-header-cell { background: #f7f9fa !important; }
    .fi-ta-header-cell, .fi-ta-header-cell button, .fi-ta-header-cell span { font-size: .76rem !important; font-weight: 700 !important; color: #475569 !important; letter-spacing: .02em; }
    .fi-ta-table > thead > tr { border-bottom: 1px solid var(--rowad-line); }
    .fi-ta-row:hover { background: #f5fbfa !important; }
    .fi-ta-row:nth-child(even) { background: #fcfdfd; }
    .fi-ta-cell { padding-top: 11px !important; padding-bottom: 11px !important; }
    .fi-ta-text-item { font-size: .9rem; }
    .fi-ta-empty-state-heading { font-weight: 700; }
    .fi-ta-empty-state-icon-bg { background: var(--rowad-brand-soft) !important; color: var(--rowad-brand) !important; }
    .fi-ta-actions { gap: 4px !important; }
    .fi-ta-actions .fi-btn, .fi-ta-actions .fi-icon-btn { border-radius: 10px !important; }
    .fi-ta-actions .fi-btn.fi-color-gray, .fi-ta-actions .fi-link.fi-color-gray { color: #475569; }
    .fi-pagination { padding-top: 10px; }

    /* ---------- badges & buttons ---------- */
    .fi-badge { border-radius: 999px !important; font-weight: 700 !important; padding: 3px 10px !important; font-size: .74rem !important; letter-spacing: .01em; }
    .fi-btn { border-radius: 11px !important; font-weight: 700 !important; }
    .fi-btn.fi-color-primary { background: linear-gradient(135deg, #14968b, #0f766e) !important; box-shadow: 0 6px 16px rgb(15 118 110 / .25) !important; }
    .fi-btn.fi-color-primary:hover { background: linear-gradient(135deg, #118579, #0b5d57) !important; }
    .fi-icon-btn { border-radius: 10px !important; }
    .fi-dropdown-panel { border-radius: 14px !important; box-shadow: 0 12px 32px rgb(15 23 42 / .14) !important; border: 1px solid var(--rowad-line) !important; }
    .fi-dropdown-list-item { border-radius: 10px !important; }

    /* ---------- KPI cards ---------- */
    .fi-wi-stats-overview-stat { position: relative; overflow: hidden; transition: transform .15s ease, box-shadow .15s ease; padding: 18px 20px !important; }
    .fi-wi-stats-overview-stat::before { content: ""; position: absolute; inset-inline-start: 0; top: 0; bottom: 0; width: 4px; background: var(--rowad-brand); opacity: .9; }
    .fi-wi-stats-overview-stat:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgb(15 23 42 / .09) !important; }
    .fi-wi-stats-overview-stat-label { color: var(--rowad-muted); font-weight: 600; font-size: .82rem; }
    .fi-wi-stats-overview-stat-value { font-weight: 800; letter-spacing: -.02em; color: var(--rowad-ink); font-size: 1.6rem; }
    .fi-wi-stats-overview-stat-description { font-size: .8rem; }
    .fi-wi-stats-overview-stat .fi-icon { color: var(--rowad-brand); }

    /* ---------- tabs ---------- */
    .fi-tabs { padding: 6px !important; }
    .fi-tabs-item.fi-active { background: var(--rowad-brand-soft) !important; color: var(--rowad-brand-dark) !important; }

    /* ---------- modals & notifications ---------- */
    .fi-modal-window { border-radius: 20px !important; }
    .fi-no-notification { border-radius: 14px !important; box-shadow: 0 12px 32px rgb(15 23 42 / .14) !important; }

    /* ---------- welcome banner ---------- */
    .rowad-welcome { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 20px; padding: 26px 28px;
        border-radius: 20px; color: #fff; position: relative; overflow: hidden;
        background: radial-gradient(1200px 300px at 10% -40%, rgb(255 255 255 / .18), transparent 60%), linear-gradient(135deg, #0f766e 0%, #115e59 55%, #134e4a 100%);
        box-shadow: 0 10px 30px rgb(15 118 110 / .25); }
    .rowad-welcome::after { content: ""; position: absolute; inset-inline-end: -60px; bottom: -80px; width: 260px; height: 260px; border-radius: 50%;
        background: rgb(255 255 255 / .06); }
    .rowad-welcome__date { font-size: .85rem; opacity: .85; margin: 0 0 4px; }
    .rowad-welcome__date span { margin: 0 6px; opacity: .6; }
    .rowad-welcome__title { font-size: 1.6rem; font-weight: 800; margin: 0; line-height: 1.4; }
    .rowad-welcome__sub { margin: 4px 0 0; opacity: .85; font-size: .95rem; }
    .rowad-welcome__actions { display: flex; flex-wrap: wrap; gap: 10px; position: relative; z-index: 1; }
    .rowad-welcome__action { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 12px; font-weight: 700; font-size: .9rem;
        background: rgb(255 255 255 / .14); color: #fff; border: 1px solid rgb(255 255 255 / .25); backdrop-filter: blur(6px); transition: background .15s ease; }
    .rowad-welcome__action:hover { background: rgb(255 255 255 / .24); }
    .rowad-welcome__icon { width: 18px; height: 18px; }

    /* ---------- record header card (view pages) ---------- */
    .rowad-record { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px 24px; padding: 20px 24px; border-radius: 20px;
        background: #fff; border: 1px solid var(--rowad-line); box-shadow: var(--rowad-shadow); border-inline-start: 5px solid var(--rowad-brand); }
    .rowad-record__main { display: flex; align-items: center; gap: 16px; min-width: 0; }
    .rowad-record__icon { width: 52px; height: 52px; flex: none; border-radius: 16px; display: grid; place-items: center; color: #fff;
        background: linear-gradient(135deg, #14b8a6, #0f766e); box-shadow: 0 8px 18px rgb(15 118 110 / .28); }
    .rowad-record__icon svg { width: 26px; height: 26px; }
    .rowad-record__kicker { margin: 0; font-size: .78rem; color: var(--rowad-muted); font-weight: 600; }
    .rowad-record__title { margin: 2px 0 0; font-size: 1.35rem; font-weight: 800; color: var(--rowad-ink); line-height: 1.3; }
    .rowad-record__sub { margin: 4px 0 0; font-size: .88rem; color: var(--rowad-muted); display: flex; flex-wrap: wrap; gap: 6px 14px; }
    .rowad-record__facts { display: flex; flex-wrap: wrap; gap: 10px; }
    .rowad-record__fact { background: var(--rowad-surface); border: 1px solid var(--rowad-line); border-radius: 14px; padding: 10px 16px; min-width: 120px; }
    .rowad-record__fact span { display: block; font-size: .74rem; color: var(--rowad-muted); font-weight: 600; }
    .rowad-record__fact b { font-size: 1.1rem; font-weight: 800; color: var(--rowad-ink); }
    .rowad-record__fact.is-danger b { color: #b91c1c; } .rowad-record__fact.is-success b { color: #047857; } .rowad-record__fact.is-warning b { color: #b45309; }
    @media (max-width: 640px) { .rowad-record { padding: 16px; } .rowad-record__facts { width: 100%; } .rowad-record__fact { flex: 1; } }
</style>
<style>
    /* developer credit (Planova) */
    /* ticket conversation thread */
    .rowad-thread { display: flex; flex-direction: column; gap: 12px; }
    .rowad-thread__msg { max-width: 85%; border-radius: 16px; padding: 12px 16px; border: 1px solid var(--rowad-line); background: #f7f9f9; }
    .rowad-thread__msg.is-me { align-self: flex-end; background: var(--rowad-brand-soft); border-color: transparent; }
    .rowad-thread__msg.is-them { align-self: flex-start; }
    .rowad-thread__meta { display: flex; gap: 10px; align-items: baseline; font-size: .78rem; color: var(--rowad-muted); margin-bottom: 4px; }
    .rowad-thread__meta b { color: var(--rowad-ink); font-size: .85rem; }
    .rowad-thread__body { line-height: 1.7; white-space: normal; color: var(--rowad-ink); }
    .rowad-thread__empty { color: var(--rowad-muted); font-size: .9rem; text-align: center; padding: 8px 0 0; }
    .dark .rowad-thread__msg { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.08); }
    .dark .rowad-thread__msg.is-me { background: rgba(20,184,166,.15); }
    .rowad-credit { text-align: center; font-size: .8rem; color: var(--rowad-muted); padding: 18px 16px 22px; }
    .rowad-credit--compact { padding: 14px 0 0; }
    .rowad-credit a { color: var(--rowad-brand); font-weight: 700; }
    .rowad-credit a:hover { text-decoration: underline; }
    .rowad-plan-notice { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin: 16px 0 4px; padding: 12px 16px; border-radius: 14px; font-weight: 600; font-size: .92rem; border: 1px solid; }
    .rowad-plan-notice--warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .rowad-plan-notice--danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .rowad-plan-notice__icon { width: 20px; height: 20px; flex: none; }
    .rowad-plan-notice a { margin-inline-start: auto; text-decoration: underline; font-weight: 700; }
    .rowad-ai-shortcut { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: .85rem; font-weight: 700; color: #fff;
        background: linear-gradient(135deg, #14b8a6, #0f766e); box-shadow: 0 4px 12px rgb(15 118 110 / .25); margin-inline-end: 6px; }
    .rowad-ai-shortcut:hover { filter: brightness(1.08); }
    .rowad-ai-shortcut svg { width: 18px; height: 18px; }
    @media (max-width: 640px) { .rowad-ai-shortcut span { display: none; } .rowad-ai-shortcut { padding: 8px; } }
</style>
