{{-- Aqar Nova panel theme on top of Filament: branded sidebar, layered cards, readable tables, Cairo throughout. --}}
<style>
    :root {
        --rowad-surface: #f3f6f7; --rowad-card: #ffffff; --rowad-line: #e4e9ec; --rowad-ink: #0f172a; --rowad-muted: #64748b;
        --rowad-brand: #0f766e; --rowad-brand-dark: #0b5d57; --rowad-brand-soft: #e6f4f2; --rowad-accent: #eb6834;
        --rowad-side: #0b2a2e; --rowad-side-2: #0e3a3f; --rowad-side-ink: #d7e6e4; --rowad-side-muted: #7fa3a0;
        --rowad-shadow: 0 1px 2px rgb(15 23 42 / .04), 0 6px 20px rgb(15 23 42 / .06);
        --rowad-radius: 18px; --rowad-brand-line: #ccfbf1; --rowad-brand-light: #14b8a6;
    }
    {!! \App\Support\Branding::css() !!}
    body.fi-body, .fi-layout, .fi-main-ctn { background: var(--rowad-surface); }
    body, .fi-body { font-feature-settings: "tnum" 1; }

    /* ---------- sidebar: deep teal, white content ---------- */
    .fi-sidebar { background: linear-gradient(180deg, var(--rowad-side) 0%, var(--rowad-side-2) 100%) !important; border-inline-end: 0 !important; box-shadow: 4px 0 24px rgb(2 20 22 / .18); }
    .fi-sidebar-header { background: transparent !important; border-bottom: 1px solid rgb(255 255 255 / .08) !important; box-shadow: none !important; }
    .fi-sidebar-header .fi-logo, .fi-sidebar-header .fi-logo * { color: #fff !important; }
    .fi-sidebar-header img.fi-logo { filter: brightness(1.15); }
    .fi-sidebar-nav { padding: 10px 10px 16px !important; row-gap: 0 !important; }
    .fi-sidebar-nav-groups { row-gap: 14px !important; margin-inline: 0 !important; }
    .fi-sidebar-group-btn { padding: 2px 8px !important; }
    .fi-sidebar-group-label { font-size: .66rem; letter-spacing: .08em; text-transform: uppercase; color: var(--rowad-side-muted) !important; font-weight: 700; line-height: 1.6rem !important; }
    .fi-sidebar-group-items { row-gap: 1px !important; }
    .fi-sidebar-group-btn .fi-icon, .fi-sidebar-group-collapse-btn .fi-icon, .fi-sidebar-group-collapse-btn svg { color: var(--rowad-side-muted) !important; }
    .fi-sidebar-item-btn { border-radius: 10px !important; padding: 6px 9px !important; column-gap: 9px !important; transition: background .15s ease, color .15s ease; }
    .fi-sidebar-item-btn:hover { background: rgb(255 255 255 / .07) !important; }
    .fi-sidebar-item-label { color: var(--rowad-side-ink) !important; font-weight: 500; font-size: .84rem; line-height: 1.5rem; }
    .fi-sidebar-item-btn .fi-icon, .fi-sidebar-item-btn > svg { color: var(--rowad-side-muted) !important; width: 1.1rem; height: 1.1rem; }
    .fi-sidebar-item-grouped-border-part { margin-inline-start: 8px; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label { font-size: .86rem; }
    /* the office's brand lives in the sidebar on desktop; the topbar keeps it only on small screens */
    @media (min-width: 64rem) {
        .fi-sidebar-header { display: flex !important; height: var(--topbar-height); }
        .fi-topbar-start > a { display: none; }
        .fi-topbar-start { margin-inline-end: 8px !important; }
    }
    .fi-sidebar-header .fi-logo { margin: 0 !important; height: auto !important; }
    .fi-sidebar-header-logo-ctn a { display: block; }
    .rowad-brand { display: inline-flex; align-items: center; gap: 10px; min-width: 0; max-width: 100%; }
    .rowad-brand__logo { height: 2.75rem; width: auto; max-width: 13.5rem; object-fit: contain; border-radius: 10px; background: #fff; padding: 4px 8px; }
    .fi-sidebar-header-logo-ctn { display: flex; justify-content: center; }
    .rowad-side-mark { display: grid; place-items: center; width: 100%; }
    .rowad-brand__mark { width: 2.5rem; height: 2.5rem; flex: none; border-radius: 12px; display: grid; place-items: center; color: #fff; font-weight: 800; font-size: 1rem; letter-spacing: .02em;
        background: linear-gradient(135deg, var(--rowad-brand-light), var(--rowad-brand) 60%, var(--rowad-brand-dark)); box-shadow: 0 6px 14px color-mix(in oklab, var(--rowad-brand) 35%, transparent); }
    .rowad-brand__mark--lg { width: 4.5rem; height: 4.5rem; border-radius: 20px; font-size: 1.8rem; }
    .rowad-brand__text { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
    .rowad-brand__name { font-weight: 800; font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--rowad-ink); }
    .rowad-brand__panel { font-size: .7rem; font-weight: 600; color: var(--rowad-muted); }
    .fi-sidebar .rowad-brand__name { color: #fff; } .fi-sidebar .rowad-brand__panel { color: var(--rowad-side-muted); }
    .fi-sidebar .rowad-brand__logo { background: rgb(255 255 255 / .92); }
    /* sidebar footer: plan / office contact + the signed-in user */
    .rowad-side-footer { padding: 10px 10px 12px; border-top: 1px solid rgb(255 255 255 / .08); display: grid; gap: 6px; }
    .rowad-side-plan, .rowad-side-user { display: flex; align-items: center; gap: 9px; padding: 7px 9px; border-radius: 10px; color: var(--rowad-side-ink); text-decoration: none; min-width: 0; }
    .rowad-side-plan { background: rgb(255 255 255 / .05); border: 1px solid rgb(255 255 255 / .08); }
    .rowad-side-plan:hover, .rowad-side-user:hover { background: rgb(255 255 255 / .09); }
    .rowad-side-plan svg { width: 15px; height: 15px; flex: none; color: var(--rowad-side-muted); margin-inline-start: auto; }
    .rowad-side-plan__dot { width: 9px; height: 9px; border-radius: 50%; flex: none; background: #34d399; box-shadow: 0 0 0 3px rgb(52 211 153 / .2); }
    .rowad-side-plan.is-warning .rowad-side-plan__dot { background: #fbbf24; box-shadow: 0 0 0 3px rgb(251 191 36 / .2); }
    .rowad-side-plan.is-danger .rowad-side-plan__dot { background: #f87171; box-shadow: 0 0 0 3px rgb(248 113 113 / .2); }
    .rowad-side-plan__text, .rowad-side-user__text { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }
    .rowad-side-plan__text b, .rowad-side-user__text b { font-size: .8rem; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rowad-side-plan__text small, .rowad-side-user__text small { font-size: .68rem; color: var(--rowad-side-muted); }
    .rowad-side-user__avatar { width: 32px; height: 32px; flex: none; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: .85rem; color: #fff;
        background: color-mix(in oklab, var(--rowad-brand-light) 45%, transparent); border: 1px solid rgb(255 255 255 / .18); }
    .fi-sidebar-footer { padding: 0 !important; }
    .fi-sidebar-footer-ctn { background: transparent !important; }
    /* quick create in the topbar (admin) */
    .rowad-quick { position: relative; margin-inline-end: 8px; }
    .rowad-quick__btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 12px 7px 10px; border-radius: 999px; font: inherit; font-size: .85rem; font-weight: 700; cursor: pointer;
        color: var(--rowad-brand-dark); background: var(--rowad-brand-soft); border: 1px solid var(--rowad-brand-line); }
    .rowad-quick__btn:hover { background: color-mix(in oklab, var(--rowad-brand-soft) 60%, var(--rowad-brand-line)); }
    .rowad-quick__btn svg { width: 16px; height: 16px; }
    .rowad-quick__menu { position: absolute; inset-inline-start: 0; top: calc(100% + 8px); min-width: 190px; background: #fff; border: 1px solid var(--rowad-line); border-radius: 14px; padding: 6px; z-index: 40;
        box-shadow: 0 12px 32px rgb(15 23 42 / .14); display: grid; }
    .rowad-quick__menu a { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 10px; font-size: .88rem; font-weight: 600; color: var(--rowad-ink); }
    .rowad-quick__menu a:hover { background: var(--rowad-brand-soft); color: var(--rowad-brand-dark); }
    .rowad-quick__menu svg { width: 18px; height: 18px; color: var(--rowad-brand); }
    @media (max-width: 640px) { .rowad-quick__btn span { display: none; } .rowad-quick__btn { padding: 8px; } }
    /* login: brand aside + form card */
    .fi-simple-layout { background: var(--rowad-surface); }
    .fi-simple-main-ctn { padding: 24px 16px; }
    .fi-simple-main { border-radius: 22px !important; box-shadow: 0 20px 60px rgb(15 23 42 / .08), 0 1px 2px rgb(15 23 42 / .04) !important; margin-block: 0 !important; }
    .rowad-login-aside { display: none; }
    @media (min-width: 56rem) {
        .fi-simple-layout { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); grid-template-rows: 1fr auto; align-items: stretch; }
        .rowad-login-aside { display: flex; flex-direction: column; justify-content: center; gap: 28px; padding: 56px 48px; color: #fff; position: relative; overflow: hidden; min-height: 100dvh; grid-row: 1 / span 2;
            background: radial-gradient(700px 360px at 20% -10%, rgb(255 255 255 / .16), transparent 60%), linear-gradient(160deg, var(--rowad-side) 0%, var(--rowad-side-2) 60%, var(--rowad-brand-dark) 100%); }
        .rowad-login-aside::after { content: ""; position: absolute; inset-inline-end: -120px; bottom: -140px; width: 380px; height: 380px; border-radius: 50%; background: rgb(255 255 255 / .05); }
        .fi-simple-main-ctn { padding: 40px 32px; }
        .fi-simple-layout > .rowad-credit, .fi-simple-layout > .fi-simple-layout-header { grid-column: 2; }
        .fi-simple-header .fi-logo { display: none; }
    }
    .rowad-login-aside__brand img { height: 72px; width: auto; max-width: 240px; object-fit: contain; background: #fff; border-radius: 16px; padding: 10px; margin-bottom: 18px; }
    .rowad-login-aside__brand .rowad-brand__mark { margin-bottom: 18px; }
    .rowad-login-aside__brand h1 { font-size: 1.7rem; font-weight: 800; margin: 0; color: #fff; line-height: 1.3; }
    .rowad-login-aside__brand p { margin: 4px 0 0; color: rgb(255 255 255 / .7); font-weight: 600; }
    .rowad-login-aside__points { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .rowad-login-aside__points li { display: flex; align-items: center; gap: 10px; font-size: .98rem; color: rgb(255 255 255 / .9); }
    .rowad-login-aside__points svg { width: 22px; height: 22px; padding: 4px; border-radius: 50%; background: rgb(255 255 255 / .14); color: #fff; flex: none; }
    .rowad-login-aside__site { color: #fff; font-weight: 700; opacity: .85; width: fit-content; } .rowad-login-aside__site:hover { opacity: 1; text-decoration: underline; }
    .fi-simple-header-heading { font-size: 1.35rem !important; }
    .fi-simple-layout .rowad-brand { flex-direction: column; text-align: center; gap: 8px; }
    .fi-simple-layout .rowad-brand__panel { display: none; }
    .fi-sidebar-item-btn { border-inline-start: 3px solid transparent; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn, .fi-sidebar-item-btn.fi-active { background: color-mix(in oklab, var(--rowad-brand-light) 15%, rgb(255 255 255 / .03)) !important; border-inline-start-color: var(--rowad-brand-light); border-radius: 8px !important; }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label, .fi-sidebar-item-btn.fi-active .fi-sidebar-item-label { color: #fff !important; font-weight: 700; }
    .fi-sidebar-item.fi-active .fi-icon, .fi-sidebar-item-btn.fi-active .fi-icon, .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > svg { color: color-mix(in oklab, var(--rowad-brand-light) 70%, #fff) !important; }
    .fi-sidebar-item-grouped-border-part { border-color: rgb(255 255 255 / .12) !important; }
    .fi-sidebar-item-badge-ctn .fi-badge { background: var(--rowad-accent) !important; color: #fff !important; border: 0 !important; }
    .fi-sidebar-database-notifications-btn-label { color: var(--rowad-side-ink) !important; }
    /* a scrollbar people can see: the menu is long and continues below the fold */
    .fi-sidebar-nav { scrollbar-width: thin; scrollbar-color: color-mix(in oklab, var(--rowad-brand-light) 75%, #fff) rgb(255 255 255 / .07); }
    .fi-sidebar::-webkit-scrollbar, .fi-sidebar-nav::-webkit-scrollbar { width: 8px; }
    .fi-sidebar-nav::-webkit-scrollbar-track { background: rgb(255 255 255 / .07); border-radius: 8px; margin-block: 6px; }
    .fi-sidebar-nav::-webkit-scrollbar-thumb { background: color-mix(in oklab, var(--rowad-brand-light) 75%, #fff); border-radius: 8px; border: 2px solid transparent; background-clip: padding-box; min-height: 40px; }
    .fi-sidebar-nav::-webkit-scrollbar-thumb:hover { background: var(--rowad-brand-light); background-clip: padding-box; }
    .rowad-nav-more { position: sticky; bottom: -16px; z-index: 2; height: 56px; margin: -56px -10px -16px; padding-bottom: 10px; display: flex; align-items: flex-end; justify-content: center;
        pointer-events: none; background: linear-gradient(to bottom, transparent, var(--rowad-side-2) 72%); transition: opacity .2s; }
    .rowad-nav-more button { pointer-events: auto; display: inline-flex; align-items: center; gap: 4px; padding: 3px 12px; border-radius: 999px; font-size: .72rem; font-weight: 700;
        color: #fff; background: rgb(255 255 255 / .12); border: 1px solid rgb(255 255 255 / .2); animation: rowad-nav-bob 1.8s ease-in-out infinite; }
    .rowad-nav-more button:hover { background: rgb(255 255 255 / .2); }
    .rowad-nav-more svg { width: 14px; height: 14px; }
    .rowad-nav-more.is-end { opacity: 0; }
    .rowad-nav-more.is-end button { pointer-events: none; }
    @keyframes rowad-nav-bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(3px); } }
    @media (prefers-reduced-motion: reduce) { .rowad-nav-more button { animation: none; } }

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
    /* dense rows: more records on screen (Filament pads both the cell and the text inside it) */
    .fi-ta-cell { padding-top: 0 !important; padding-bottom: 0 !important; }
    .fi-ta-text:not(.fi-inline), .fi-ta-icon:not(.fi-inline), .fi-ta-color:not(.fi-inline), .fi-ta-image:not(.fi-inline),
    .fi-ta-text-input:not(.fi-inline), .fi-ta-select:not(.fi-inline), .fi-ta-toggle:not(.fi-inline) { padding-block: 7px !important; padding-inline: 10px !important; }
    .fi-ta-actions { padding-block: 5px; }
    .fi-ta-text-item { font-size: .875rem; line-height: 1.45; }
    .fi-ta-text-description, .fi-ta-col .fi-ta-text-description { font-size: .76rem !important; line-height: 1.35 !important; margin-top: 1px; }
    .fi-ta-header-cell { padding-top: 9px !important; padding-bottom: 9px !important; }
    .fi-ta-text-has-badges .fi-badge { padding-block: 1px !important; }
    .fi-ta-empty-state-heading { font-weight: 700; }
    .fi-ta-empty-state-icon-bg { background: var(--rowad-brand-soft) !important; color: var(--rowad-brand) !important; }
    .fi-ta-actions { gap: 4px !important; }
    .fi-ta-actions .fi-btn, .fi-ta-actions .fi-icon-btn { border-radius: 10px !important; }
    .fi-ta-actions .fi-btn.fi-color-gray, .fi-ta-actions .fi-link.fi-color-gray { color: #475569; }
    .fi-pagination { padding-top: 10px; }

    /* ---------- badges & buttons ---------- */
    .fi-badge { border-radius: 999px !important; font-weight: 700 !important; padding: 3px 10px !important; font-size: .74rem !important; letter-spacing: .01em; }
    .fi-btn { border-radius: 11px !important; font-weight: 700 !important; }
    .fi-btn.fi-color-primary { background: linear-gradient(135deg, var(--rowad-brand-light), var(--rowad-brand)) !important; box-shadow: 0 6px 16px color-mix(in oklab, var(--rowad-brand) 30%, transparent) !important; }
    .fi-btn.fi-color-primary:hover { background: linear-gradient(135deg, var(--rowad-brand), var(--rowad-brand-dark)) !important; }
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
        background: radial-gradient(1200px 300px at 10% -40%, rgb(255 255 255 / .18), transparent 60%), linear-gradient(135deg, var(--rowad-brand) 0%, var(--rowad-brand-dark) 60%, color-mix(in oklab, var(--rowad-brand-dark) 80%, #000) 100%);
        box-shadow: 0 10px 30px color-mix(in oklab, var(--rowad-brand) 30%, transparent); }
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

    /* ---------- portals on a phone: bottom tab bar + install hint ---------- */
    .rowad-tabbar, .rowad-install { display: none; }
    @media (max-width: 1023px) {
        body:has(.rowad-tabbar) .fi-main-ctn { padding-bottom: calc(76px + env(safe-area-inset-bottom)); }
        .rowad-tabbar { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; position: fixed; inset-inline: 0; bottom: 0; z-index: 35;
            background: rgb(255 255 255 / .96); backdrop-filter: blur(12px); border-top: 1px solid var(--rowad-line);
            padding: 6px 6px calc(6px + env(safe-area-inset-bottom)); box-shadow: 0 -6px 20px rgb(15 23 42 / .06); }
        .rowad-tabbar__item { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 6px 2px; border-radius: 12px; color: var(--rowad-muted);
            font: inherit; font-size: .68rem; font-weight: 700; background: none; border: 0; cursor: pointer; min-width: 0; }
        .rowad-tabbar__item span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .rowad-tabbar__icon { width: 22px; height: 22px; }
        .rowad-tabbar__item.is-active { color: var(--rowad-brand); background: var(--rowad-brand-soft); }
        .rowad-install { display: flex; align-items: center; gap: 10px; position: fixed; inset-inline: 12px; bottom: calc(84px + env(safe-area-inset-bottom)); z-index: 36;
            background: #fff; border: 1px solid var(--rowad-line); border-radius: 16px; padding: 10px 12px; box-shadow: 0 16px 40px rgb(15 23 42 / .18); }
        .rowad-install[x-cloak] { display: none !important; }
        .rowad-install img { border-radius: 10px; flex: none; }
        .rowad-install__text { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.35; }
        .rowad-install__text b { font-size: .88rem; color: var(--rowad-ink); }
        .rowad-install__text small { font-size: .74rem; color: var(--rowad-muted); }
        .rowad-install__go { font: inherit; font-size: .8rem; font-weight: 800; color: #fff; background: var(--rowad-brand); border: 0; border-radius: 999px; padding: 6px 14px; cursor: pointer; }
        .rowad-install__close { font: inherit; font-size: 1.3rem; line-height: 1; color: var(--rowad-muted); background: none; border: 0; cursor: pointer; padding: 2px 6px; }
    }

    /* ---------- insights: payment risk + rent advice ---------- */
    .rowad-risk__top { display: flex; align-items: center; gap: 14px; }
    .rowad-risk__gauge { --c: #10b981; width: 64px; height: 64px; flex: none; border-radius: 50%; display: grid; place-items: center;
        background: conic-gradient(var(--c) calc(var(--p) * 1%), #e8eef0 0); position: relative; }
    .rowad-risk__gauge::before { content: ""; position: absolute; inset: 7px; border-radius: 50%; background: #fff; }
    .rowad-risk__gauge span { position: relative; font-weight: 800; font-size: 1.15rem; color: var(--rowad-ink); }
    .rowad-risk.is-warn .rowad-risk__gauge { --c: #f59e0b; } .rowad-risk.is-bad .rowad-risk__gauge { --c: #ef4444; } .rowad-risk.is-muted .rowad-risk__gauge { --c: #cbd5e1; }
    .rowad-risk__label { margin: 0; font-size: .76rem; color: var(--rowad-muted); font-weight: 600; }
    .rowad-risk__level { margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--rowad-ink); }
    .rowad-risk.is-ok .rowad-risk__level { color: #047857; } .rowad-risk.is-warn .rowad-risk__level { color: #b45309; } .rowad-risk.is-bad .rowad-risk__level { color: #b91c1c; }
    .rowad-risk__reasons { margin: 12px 0 0; padding-inline-start: 18px; display: grid; gap: 4px; font-size: .86rem; color: #334155; }
    .rowad-risk__foot { margin: 10px 0 0; font-size: .72rem; color: var(--rowad-muted); }
    .rowad-advice__kicker { margin: 0; font-size: .76rem; color: var(--rowad-muted); font-weight: 600; }
    .rowad-advice__value { margin: 2px 0 10px; font-size: 1.6rem; font-weight: 800; color: var(--rowad-ink); letter-spacing: -.01em; }
    .rowad-advice__value small { font-size: .85rem; color: var(--rowad-muted); font-weight: 700; }
    .rowad-advice__bar { position: relative; height: 8px; border-radius: 999px; background: linear-gradient(90deg, var(--rowad-brand-soft), var(--rowad-brand-light), var(--rowad-brand-soft)); }
    .rowad-advice__bar i { position: absolute; top: -4px; width: 4px; height: 16px; border-radius: 4px; background: var(--rowad-ink); transform: translateX(50%); }
    [dir="ltr"] .rowad-advice__bar i { transform: translateX(-50%); }
    .rowad-advice__ends { display: flex; justify-content: space-between; font-size: .74rem; color: var(--rowad-muted); margin-top: 4px; font-variant-numeric: tabular-nums; }
    .rowad-advice__gap { margin: 12px 0 0; font-size: .86rem; font-weight: 700; color: #047857; }
    .rowad-advice__gap.is-under { color: #b45309; } .rowad-advice__gap.is-over { color: #b91c1c; }
    .rowad-advice__basis { margin: 8px 0 0; font-size: .74rem; color: var(--rowad-muted); }
    .rowad-advice__empty { margin: 0; font-size: .86rem; color: var(--rowad-muted); }
    .rowad-riskl { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
    .rowad-riskl__row { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 14px; border: 1px solid var(--rowad-line); background: #fbfcfc; }
    .rowad-riskl__score { width: 40px; height: 40px; flex: none; border-radius: 12px; display: grid; place-items: center; font-weight: 800; color: #92400e; background: #fef3c7; }
    .rowad-riskl__row.is-high .rowad-riskl__score { color: #991b1b; background: #fee2e2; }
    .rowad-riskl__text { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.35; }
    .rowad-riskl__text b { font-size: .9rem; color: var(--rowad-ink); }
    .rowad-riskl__text a:hover b { color: var(--rowad-brand); }
    .rowad-riskl__text small { font-size: .74rem; color: var(--rowad-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rowad-riskl__amount { font-weight: 800; color: #b91c1c; font-size: .88rem; font-variant-numeric: tabular-nums; }
    .rowad-riskl__call { width: 34px; height: 34px; flex: none; border-radius: 10px; display: grid; place-items: center; color: var(--rowad-brand); background: var(--rowad-brand-soft); }
    .rowad-riskl__call svg { width: 18px; height: 18px; }

    /* ---------- owner portfolio performance ---------- */
    .rowad-perf { display: grid; gap: 18px; }
    .rowad-perf__bar { display: flex; align-items: center; gap: 10px; justify-content: flex-end; }
    .rowad-perf__bar label { font-size: .85rem; color: var(--rowad-muted); font-weight: 600; }
    .rowad-perf__bar select { font: inherit; font-weight: 700; padding: 6px 30px 6px 12px; border: 1px solid var(--rowad-line); border-radius: 10px; background-color: #fff; }
    .rowad-perf__kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
    .rowad-perf__kpi { background: #fff; border: 1px solid var(--rowad-line); border-radius: 16px; padding: 14px 16px; box-shadow: var(--rowad-shadow); display: flex; flex-direction: column; }
    .rowad-perf__kpi span { font-size: .76rem; color: var(--rowad-muted); font-weight: 600; }
    .rowad-perf__kpi b { font-size: 1.35rem; font-weight: 800; color: var(--rowad-ink); font-variant-numeric: tabular-nums; margin-top: 2px; }
    .rowad-perf__kpi small { font-size: .72rem; color: var(--rowad-muted); }
    .rowad-perf__kpi.is-main { background: linear-gradient(135deg, var(--rowad-brand), var(--rowad-brand-dark)); border-color: transparent; }
    .rowad-perf__kpi.is-main span, .rowad-perf__kpi.is-main small { color: rgb(255 255 255 / .8); } .rowad-perf__kpi.is-main b { color: #fff; }
    .rowad-perf__kpi.is-bad b { color: #b91c1c; }
    .rowad-perf__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
    .rowad-perf__unit { background: #fff; border: 1px solid var(--rowad-line); border-radius: 18px; padding: 16px 18px; box-shadow: var(--rowad-shadow); display: grid; gap: 12px; }
    .rowad-perf__unit header { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .rowad-perf__unit h3 { margin: 0; font-size: 1rem; font-weight: 800; color: var(--rowad-ink); }
    .rowad-perf__share { font-size: .72rem; font-weight: 700; color: var(--rowad-brand-dark); background: var(--rowad-brand-soft); border-radius: 999px; padding: 2px 8px; }
    .rowad-perf__unit dl { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px 12px; }
    .rowad-perf__unit dt { font-size: .72rem; color: var(--rowad-muted); }
    .rowad-perf__unit dd { margin: 0; font-weight: 700; font-variant-numeric: tabular-nums; color: #334155; }
    .rowad-perf__unit dd.is-strong { font-size: 1.1rem; font-weight: 800; color: var(--rowad-ink); }
    .rowad-perf__unit dd.is-bad { color: #b91c1c; }
    .rowad-perf__occbar { height: 8px; border-radius: 999px; background: var(--rowad-surface); overflow: hidden; }
    .rowad-perf__occbar i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--rowad-brand-light), var(--rowad-brand)); }
    .rowad-perf__occ span { display: block; margin-top: 4px; font-size: .74rem; color: var(--rowad-muted); }
    .rowad-perf__unit footer { display: flex; flex-wrap: wrap; gap: 6px; }
    .rowad-perf__unit footer:empty { display: none; }
    .rowad-perf__chip { font-size: .74rem; font-weight: 700; border-radius: 999px; padding: 3px 10px; color: #047857; background: #ecfdf5; }
    .rowad-perf__chip.is-warn { color: #b45309; background: #fffbeb; }
    .rowad-perf__note, .rowad-perf__empty { margin: 0; font-size: .78rem; color: var(--rowad-muted); }

    /* ---------- e-invoicing (ZATCA phase 2) ---------- */
    .rowad-einv { display: grid; gap: 18px; }
    .rowad-einv__hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 18px; padding: 22px 24px; border-radius: 20px; color: #fff;
        background: linear-gradient(135deg, var(--rowad-brand), var(--rowad-brand-dark)); box-shadow: var(--rowad-shadow); }
    .rowad-einv__hero.is-off { background: linear-gradient(135deg, #475569, #1e293b); }
    .rowad-einv__state { display: flex; align-items: center; gap: 14px; }
    .rowad-einv__dot { width: 14px; height: 14px; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 5px rgb(52 211 153 / .25); flex: none; }
    .rowad-einv__hero.is-off .rowad-einv__dot { background: #fbbf24; box-shadow: 0 0 0 5px rgb(251 191 36 / .25); }
    .rowad-einv__kicker { margin: 0; font-size: .8rem; opacity: .8; }
    .rowad-einv__hero h2 { margin: 2px 0; font-size: 1.3rem; font-weight: 800; color: #fff; }
    .rowad-einv__meta { margin: 0; font-size: .82rem; opacity: .85; }
    .rowad-einv__counts { display: flex; flex-wrap: wrap; gap: 10px; }
    .rowad-einv__counts div { background: rgb(255 255 255 / .12); border: 1px solid rgb(255 255 255 / .18); border-radius: 14px; padding: 8px 14px; min-width: 96px; }
    .rowad-einv__counts b { display: block; font-size: 1.25rem; font-weight: 800; }
    .rowad-einv__counts span { font-size: .72rem; opacity: .85; }
    .rowad-einv__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
    .rowad-einv__card { background: #fff; border: 1px solid var(--rowad-line); border-radius: 18px; padding: 18px 20px; box-shadow: var(--rowad-shadow); }
    .rowad-einv__card h3 { margin: 0 0 10px; font-size: 1rem; font-weight: 800; color: var(--rowad-ink); }
    .rowad-einv__card p { margin: 0 0 8px; font-size: .88rem; color: #334155; }
    .rowad-einv__ok { color: #047857 !important; font-weight: 700; }
    .rowad-einv__list, .rowad-einv__steps { margin: 0; padding-inline-start: 20px; display: grid; gap: 6px; font-size: .88rem; color: #334155; }
    .rowad-einv__list li { color: #b45309; font-weight: 600; }
    .rowad-einv__link { display: inline-block; margin-top: 10px; font-weight: 700; color: var(--rowad-brand); font-size: .88rem; }
    .rowad-einv__table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    .rowad-einv__table th { text-align: start; font-size: .74rem; color: var(--rowad-muted); font-weight: 700; padding: 8px 10px; border-bottom: 1px solid var(--rowad-line); }
    .rowad-einv__table td { padding: 9px 10px; border-bottom: 1px solid var(--rowad-line); font-variant-numeric: tabular-nums; }
    .rowad-einv__table a { color: var(--rowad-brand); font-weight: 700; }
    .rowad-einv__badge { display: inline-block; font-size: .74rem; font-weight: 700; border-radius: 999px; padding: 2px 10px; background: #f1f5f9; color: #475569; }
    .rowad-einv__badge.is-ok { background: #ecfdf5; color: #047857; } .rowad-einv__badge.is-warn { background: #fffbeb; color: #b45309; } .rowad-einv__badge.is-bad { background: #fef2f2; color: #b91c1c; }

    /* ---------- App shell: full-height navy sidebar, page title in the topbar, command search ---------- */
    @media (min-width: 64rem) {
        .fi-body-has-navigation:not(.fi-body-has-top-navigation) .fi-main-sidebar {
            top: 0 !important; height: 100dvh !important; margin-top: calc(var(--rowad-topbar-h, var(--topbar-height)) * -1); z-index: 31;
        }
        .fi-body-has-navigation:not(.fi-body-has-top-navigation):has(.fi-main-sidebar.fi-sidebar-open) .fi-topbar { padding-inline-start: calc(var(--sidebar-width) + 1rem); }
        .fi-body-has-navigation:not(.fi-body-has-top-navigation):has(.fi-main-sidebar:not(.fi-sidebar-open)) .fi-topbar { padding-inline-start: calc(var(--collapsed-sidebar-width) + 1rem); }
        .fi-topbar { transition: padding-inline-start .2s ease; }
        .fi-sidebar-header-logo-ctn { justify-content: flex-start; padding-inline: 8px; }
    }
    .fi-sidebar { background: linear-gradient(180deg, var(--rowad-side) 0%, var(--rowad-side-2) 100%) !important; }
    /* menu filter */
    .rowad-nav-filter { position: relative; margin: 2px 0 12px; }
    .rowad-nav-filter svg { position: absolute; inset-inline-start: 11px; top: 50%; translate: 0 -50%; width: 16px; height: 16px; color: var(--rowad-side-muted); pointer-events: none; }
    .rowad-nav-filter input { width: 100%; font: inherit; font-size: .84rem; color: #fff; background: rgb(255 255 255 / .05); border: 1px solid rgb(255 255 255 / .1); border-radius: 10px;
        padding: 8px 12px; padding-inline-start: 34px; outline: none; transition: border-color .15s, background .15s; }
    .rowad-nav-filter input::placeholder { color: var(--rowad-side-muted); }
    .rowad-nav-filter input:focus { background: rgb(255 255 255 / .08); border-color: color-mix(in oklab, var(--rowad-brand-light) 65%, transparent); }
    .rowad-nav-filter input::-webkit-search-cancel-button { filter: invert(1) opacity(.5); }
    .rowad-nav-filtering .fi-sidebar-group-items { display: flex !important; }
    /* topbar */
    .fi-topbar { background: #fff !important; backdrop-filter: none; box-shadow: 0 1px 0 var(--rowad-line), 0 4px 14px rgb(15 23 42 / .03) !important; }
    .fi-topbar-start { min-width: 0; flex: 1 1 auto; }
    .rowad-top-title { display: none; }
    @media (min-width: 64rem) {
        .rowad-top-title { display: flex; flex-direction: column; justify-content: center; min-width: 0; line-height: 1.35; margin-inline-start: 6px; }
        .rowad-top-title__crumbs .fi-breadcrumbs-item-label { font-size: .74rem; }
        .rowad-top-title__crumbs .fi-breadcrumbs-item-separator { width: 12px; height: 12px; }
        .rowad-top-title__name { font-weight: 800; font-size: 1.05rem; color: var(--rowad-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rowad-top-title__crumbs:empty + .rowad-top-title__name { font-size: 1.12rem; }
        body.rowad-has-top-title .fi-main .fi-header .fi-breadcrumbs { display: none; }
    }
    .fi-topbar-end { gap: 4px; flex: none; }
    .rowad-top-title { flex: 1 1 auto; overflow: hidden; }
    .rowad-ai-shortcut, .rowad-quick__btn, .rowad-top-user__text b { white-space: nowrap; }
    .fi-topbar-end > .fi-user-menu { order: 1; margin-inline-start: 8px; padding-inline-start: 12px; border-inline-start: 1px solid var(--rowad-line); }
    .fi-topbar-end > .rowad-top-user { order: 2; }
    .fi-topbar .fi-user-menu-trigger .fi-avatar { width: 2.5rem; height: 2.5rem; }
    .fi-topbar .fi-global-search-field .fi-input-wrp { border-radius: 12px; background: #f6f8fb; border-color: var(--rowad-line); box-shadow: none; }
    .fi-topbar .fi-global-search-field .fi-input-wrp:focus-within { background: #fff; }
    @media (min-width: 64rem) { .fi-topbar .fi-global-search-field { width: 19rem; } }
    .fi-topbar .fi-global-search-field .fi-input-wrp-suffix { border: 0 !important; background: transparent !important; padding-inline: 0 10px !important; }
    .fi-topbar .fi-global-search-field .fi-input-wrp-label { font-size: .68rem; font-weight: 700; color: var(--rowad-muted); white-space: nowrap; direction: ltr;
        background: #fff; border: 1px solid var(--rowad-line); border-bottom-width: 2px; border-radius: 6px; padding: 0 6px; line-height: 1.6; }
    @media (max-width: 48rem) { .fi-topbar .fi-global-search-field .fi-input-wrp-suffix { display: none; } }
    /* records search on its own row under the top bar; the top row keeps the Ctrl+K screen launcher */
    @media (min-width: 48rem) {
        .fi-topbar { flex-wrap: wrap; height: auto !important; min-height: 4rem; row-gap: 0; }
        .fi-topbar-end { display: contents; }
        .fi-topbar > .rowad-screens { margin-inline-start: auto; }
        .fi-topbar .fi-global-search-ctn { order: 99; flex: 1 0 100%; display: flex; padding: 8px 0 10px; margin-top: 4px; border-top: 1px solid var(--rowad-line); }
        .fi-topbar .fi-global-search-ctn > div { width: min(44rem, 100%); }
        .fi-topbar .fi-global-search-field { width: 100% !important; }
        .fi-topbar .fi-global-search-results-ctn { width: min(44rem, 92vw) !important; }
    }
    .rowad-screens__trigger { display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 10px 0 12px; border-radius: 12px; border: 1px solid var(--rowad-line);
        background: #f6f8fb; color: var(--rowad-muted); font: inherit; font-size: .86rem; cursor: pointer; white-space: nowrap; min-width: 15rem; }
    .rowad-screens__trigger:hover { background: #fff; border-color: color-mix(in oklab, var(--rowad-brand) 35%, var(--rowad-line)); color: var(--rowad-ink); }
    .rowad-screens__trigger svg { width: 18px; height: 18px; flex: none; }
    .rowad-screens__trigger-text { flex: 1; text-align: start; }
    .rowad-screens kbd, .rowad-screens__overlay kbd { font: 700 .68rem/1 ui-sans-serif, system-ui, sans-serif; color: #475569; background: #fff; border: 1px solid #e2e8f0; border-bottom-width: 2px; border-radius: 7px; padding: 4px 7px; }
    @media (max-width: 64rem) { .rowad-screens__trigger { min-width: 0; } .rowad-screens__trigger-text { display: none; } }
    @media (max-width: 48rem) { .rowad-screens { display: none; } }
    .rowad-screens__overlay { position: fixed; inset: 0; z-index: 60; background: rgb(15 23 42 / .45); backdrop-filter: blur(2px); display: flex; align-items: flex-start; justify-content: center; padding: 6vh 16px 16px; }
    .rowad-screens__dialog { width: min(720px, 100%); max-height: 82vh; display: flex; flex-direction: column; background: #fff; border-radius: 20px; box-shadow: 0 30px 80px rgb(15 23 42 / .35); overflow: hidden; }
    .rowad-screens__head { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--rowad-line); }
    .rowad-screens__head svg { width: 22px; height: 22px; color: #475569; flex: none; }
    .rowad-screens__head input { flex: 1; border: 0; outline: 0; box-shadow: none !important; font: inherit; font-size: 1.1rem; color: var(--rowad-ink); background: transparent; padding: 4px 0; }
    .rowad-screens__head kbd { cursor: pointer; }
    .rowad-screens__list { overflow-y: auto; padding: 8px 14px 12px; scrollbar-width: thin; }
    .rowad-screens__group { margin: 12px 8px 6px; font-size: .78rem; font-weight: 800; color: var(--rowad-muted); }
    .rowad-screens__item { display: flex; align-items: center; gap: 14px; padding: 9px 10px; border-radius: 14px; color: var(--rowad-ink); text-decoration: none; }
    .rowad-screens__item.is-active { background: color-mix(in oklab, var(--rowad-brand) 9%, #fff); }
    .rowad-screens__icon { width: 40px; height: 40px; flex: none; display: grid; place-items: center; border-radius: 12px; border: 1px solid var(--rowad-line); background: #f8fafc; color: #475569; }
    .rowad-screens__icon svg { width: 20px; height: 20px; }
    .rowad-screens__item.is-active .rowad-screens__icon { background: var(--rowad-brand); border-color: var(--rowad-brand); color: #fff; }
    .rowad-screens__label { flex: 1; font-weight: 700; font-size: .98rem; }
    .rowad-screens__item.is-current .rowad-screens__label::after { content: '•'; color: var(--rowad-brand); margin-inline-start: 8px; }
    .rowad-screens__meta { font-size: .78rem; color: #94a3b8; }
    .rowad-screens__empty { text-align: center; color: var(--rowad-muted); padding: 32px 0; margin: 0; }
    .rowad-screens__foot { display: flex; flex-wrap: wrap; gap: 8px 18px; align-items: center; padding: 11px 20px; border-top: 1px solid var(--rowad-line); background: #f8fafc; font-size: .8rem; color: var(--rowad-muted); }
    .rowad-screens__foot span { display: inline-flex; align-items: center; gap: 5px; }
    .rowad-screens__tip { margin-inline-start: auto; }
    .rowad-top-tool { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 10px; color: #475569; background: transparent; border: 0; cursor: pointer; }
    .rowad-top-tool:hover { background: #f1f5f9; color: var(--rowad-ink); }
    .rowad-top-tool svg { width: 20px; height: 20px; }
    .rowad-top-user { display: none; }
    @media (min-width: 48rem) {
        .rowad-top-user { display: inline-flex; align-items: center; gap: 8px; background: none; border: 0; padding: 4px 2px; cursor: pointer; font: inherit; text-align: start; }
        .rowad-top-user__text { display: flex; flex-direction: column; line-height: 1.3; }
        .rowad-top-user__text b { font-size: .84rem; font-weight: 700; color: var(--rowad-ink); white-space: nowrap; }
        .rowad-top-user__text small { font-size: .72rem; color: var(--rowad-muted); white-space: nowrap; }
        .rowad-top-user svg { width: 15px; height: 15px; color: var(--rowad-muted); }
    }
    @media (max-width: 64rem) { .rowad-top-tool { display: none; } }
    /* command search results: wider, one line of details per result */
    @media (min-width: 48rem) { .fi-topbar .fi-global-search-results-ctn { width: min(34rem, 90vw); } }
    .fi-global-search-result-group-header { font-size: .72rem; font-weight: 800; letter-spacing: .02em; color: var(--rowad-muted); background: #f8fafc; }
    .fi-global-search-result-link { padding-block: 9px !important; }
    .fi-global-search-result-heading { font-weight: 700; color: var(--rowad-ink); }
    .fi-global-search-result-details { display: flex; flex-wrap: wrap; column-gap: 14px; row-gap: 0; margin-top: 2px; }
    .fi-global-search-result-detail { font-size: .76rem; color: var(--rowad-muted); }
    .fi-global-search-result:hover .fi-global-search-result-heading, .fi-global-search-result:focus-within .fi-global-search-result-heading { color: var(--rowad-brand); }
    /* report PDFs: what is captured drops the filters and actions, and gains the office / period heading */
    .rowad-pdf-capture .rowad-acc-bar, .rowad-pdf-capture .fi-ta-header-toolbar, .rowad-pdf-capture .fi-ta-filters-above-content-ctn,
    .rowad-pdf-capture .fi-ta-pagination, .rowad-pdf-capture .fi-ta-actions { display: none !important; }
    .rowad-pdf-capture .rowad-acc-print-head { display: block !important; margin-bottom: 12px; }
    .rowad-pdf-capture .rowad-acc-card, .rowad-pdf-capture .fi-ta-ctn { box-shadow: none !important; }
    /* page header as a card: the section's icon, its title and the page actions */
    .fi-main .fi-header { background: var(--rowad-card); border: 1px solid var(--rowad-line); border-radius: 16px; box-shadow: var(--rowad-shadow); padding: 14px 20px !important; align-items: center; }
    .fi-main .fi-header .fi-header-heading { font-size: 1.25rem; display: flex; align-items: center; gap: 12px; }
    .rowad-head-icon { display: inline-grid; place-items: center; width: 40px; height: 40px; flex: none; border-radius: 12px; background: var(--rowad-brand-soft); color: var(--rowad-brand); }
    .rowad-head-icon svg { width: 22px; height: 22px; color: inherit !important; }
    /* a header with nothing but its title (the dashboard…) adds nothing once the title is in the topbar */
    @media (min-width: 64rem) { .fi-main .fi-header:not(:has(.fi-header-actions-ctn)):not(.fi-header-has-subheading) { display: none; } }

    /* ---------- Platform console: login panel, AI status ---------- */
    .rowad-login-aside--platform { background: radial-gradient(640px 340px at 85% 0%, rgb(20 184 166 / .28), transparent 60%),
        radial-gradient(520px 300px at 0% 100%, rgb(235 104 52 / .16), transparent 60%), linear-gradient(160deg, #071c1f 0%, #0b2a2e 55%, #0b4a45 100%) !important; }
    .rowad-plat-mark { display: inline-grid; place-items: center; width: 68px; height: 68px; border-radius: 20px; background: #fff; margin-bottom: 18px;
        box-shadow: 0 12px 30px rgb(0 0 0 / .25); }
    .rowad-plat-mark img { width: 42px; height: 42px; }
    .rowad-login-aside--platform h1 { font-size: 2rem !important; letter-spacing: -.01em; }
    .rowad-login-aside--platform h1 span { color: #5eead4; }
    .rowad-plat-note { display: flex; align-items: flex-start; gap: 10px; margin: 0; max-width: 30rem; font-size: .86rem; line-height: 1.7; color: rgb(255 255 255 / .72);
        background: rgb(255 255 255 / .06); border: 1px solid rgb(255 255 255 / .1); border-radius: 14px; padding: 12px 14px; }
    .rowad-plat-note svg { width: 20px; height: 20px; flex: none; margin-top: 2px; color: #5eead4; }
    .rowad-ai-status { margin: 0; padding: 10px 14px; border-radius: 12px; font-weight: 700; font-size: .88rem; }
    .rowad-ai-status.is-ok { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .rowad-ai-status.is-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

    /* ---------- Public demo banner ---------- */
    .rowad-demo-banner { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 4px 12px; padding: 7px 16px; font-size: .82rem;
        background: linear-gradient(90deg, var(--rowad-brand-dark), var(--rowad-brand)); color: #ecfdf5; text-align: center; }
    .rowad-demo-banner strong { background: rgb(255 255 255 / .16); border-radius: 999px; padding: 1px 10px; font-weight: 800; }
    .rowad-demo-banner a { color: #fff; font-weight: 800; text-decoration: underline; text-underline-offset: 3px; }
    .rowad-demo-banner .is-short { display: none; }
    @media (max-width: 640px) { .rowad-demo-banner { font-size: .76rem; padding: 6px 10px; } .rowad-demo-banner .is-long { display: none; } .rowad-demo-banner .is-short { display: inline; } }

    /* ---------- Data import from Excel ---------- */
    .rowad-import { display: grid; gap: 16px; }
    .rowad-import__steps { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .rowad-import__step { position: relative; background: #fff; border: 1px solid var(--rowad-line); border-radius: 16px; box-shadow: var(--rowad-shadow); transition: border-color .15s, box-shadow .15s; }
    .rowad-import__step.is-active { border-color: var(--rowad-brand); box-shadow: 0 0 0 3px var(--rowad-brand-soft), var(--rowad-shadow); }
    .rowad-import__pick { display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; align-items: center; width: 100%; padding: 14px 16px 8px; text-align: start; cursor: pointer; background: none; border: 0; }
    .rowad-import__num { grid-row: span 2; width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; background: var(--rowad-brand-soft); color: var(--rowad-brand-dark); }
    .rowad-import__step.is-active .rowad-import__num { background: var(--rowad-brand); color: #fff; }
    .rowad-import__name { font-weight: 800; color: var(--rowad-ink); font-size: .95rem; }
    .rowad-import__count { font-size: .78rem; color: var(--rowad-muted); }
    .rowad-import__tpl { padding: 0 16px 12px; padding-inline-start: 62px; }
    .rowad-import__grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (max-width: 1024px) { .rowad-import__grid { grid-template-columns: 1fr; } }
    .rowad-import__buttons { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
    .rowad-import__cols { list-style: none; margin: 0 0 10px; padding: 0; display: flex; flex-wrap: wrap; gap: 6px; }
    .rowad-import__cols li { font-size: .78rem; border-radius: 999px; padding: 3px 10px; background: #f1f5f9; color: #475569; }
    .rowad-import__cols li.is-required { background: var(--rowad-brand-soft); color: var(--rowad-brand-dark); font-weight: 700; }
    .rowad-import__tip { font-size: .82rem !important; color: var(--rowad-muted) !important; border-top: 1px dashed var(--rowad-line); padding-top: 10px; }
    .rowad-import__preview { display: grid; gap: 12px; }
    .rowad-import__preview h3 { margin: 0; }
    .rowad-import__stats { display: flex; flex-wrap: wrap; gap: 10px; }
    .rowad-import__stats div { border: 1px solid var(--rowad-line); border-radius: 14px; padding: 8px 16px; min-width: 120px; background: #f8fafc; }
    .rowad-import__stats b { display: block; font-size: 1.3rem; font-weight: 800; color: var(--rowad-ink); font-variant-numeric: tabular-nums; }
    .rowad-import__stats span { font-size: .76rem; color: var(--rowad-muted); }
    .rowad-import__stats .is-ok { background: #ecfdf5; border-color: #a7f3d0; } .rowad-import__stats .is-ok b { color: #047857; }
    .rowad-import__stats .is-bad { background: #fef2f2; border-color: #fecaca; } .rowad-import__stats .is-bad b { color: #b91c1c; }
    .rowad-import__note { font-size: .82rem; color: var(--rowad-muted); margin: 0; }
    .rowad-payout__bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
    .rowad-payout__table td { vertical-align: middle; }
    .rowad-payout__table td small { display: block; font-size: .74rem; color: var(--rowad-muted); }
    .rowad-payout__table .is-num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .rowad-payout__table td.is-bad { color: #b91c1c; }
    .rowad-payout__table tr.is-off td { color: var(--rowad-muted); }
    .rowad-payout__table .rowad-einv__link { margin: 0 8px 0 0; font-size: .8rem; }
    .rowad-payout__iban { font-family: ui-monospace, monospace; font-size: .8rem; color: var(--rowad-ink); }
    .rowad-payout__check { width: 16px; height: 16px; accent-color: var(--rowad-brand); cursor: pointer; }

    /* ---------- Ejar data sheet and readiness ---------- */
    .rowad-ejar { display: grid; gap: 14px; }
    .rowad-ejar__alert, .rowad-ejar__ok { border-radius: 14px; padding: 12px 16px; font-size: .86rem; display: grid; gap: 2px; }
    .rowad-ejar__alert { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .rowad-ejar__ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; font-weight: 700; }
    .rowad-ejar__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; }
    .rowad-ejar__card { border: 1px solid var(--rowad-line); border-radius: 16px; padding: 12px 14px; background: #fff; }
    .rowad-ejar__card h3 { margin: 0 0 6px; font-size: .9rem; font-weight: 800; color: var(--rowad-brand-dark); }
    .rowad-ejar__card dl { margin: 0; }
    .rowad-ejar__row { display: grid; grid-template-columns: 42% 1fr; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px dashed var(--rowad-line); font-size: .84rem; }
    .rowad-ejar__row:last-child { border-bottom: 0; }
    .rowad-ejar__row dt { color: var(--rowad-muted); }
    .rowad-ejar__row dd { margin: 0; display: flex; align-items: center; justify-content: space-between; gap: 8px; font-weight: 700; color: var(--rowad-ink); min-width: 0; }
    .rowad-ejar__row dd > span:first-child { overflow-wrap: anywhere; }
    .rowad-ejar__row.is-missing dt { color: #b91c1c; font-weight: 700; }
    .rowad-ejar__copy { flex: none; font: inherit; font-size: .72rem; font-weight: 700; color: var(--rowad-brand); background: var(--rowad-surface); border: 1px solid var(--rowad-line); border-radius: 999px; padding: 2px 10px; cursor: pointer; }
    .rowad-ejar__copy.is-done { color: #047857; background: #ecfdf5; border-color: #a7f3d0; }
    .rowad-ejar__need { font-size: .74rem; font-weight: 700; color: #b91c1c; background: #fef2f2; border-radius: 999px; padding: 2px 10px; }
    .rowad-ejar__none { color: var(--rowad-muted); font-weight: 400; }
    .rowad-ejar-ready { display: grid; gap: 6px; }
    .rowad-ejar-ready__bar { height: 8px; border-radius: 999px; background: var(--rowad-surface); overflow: hidden; }
    .rowad-ejar-ready__bar span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #fbbf24, #f59e0b); }
    .rowad-ejar-ready__bar span.is-full { background: linear-gradient(90deg, var(--rowad-brand-light), var(--rowad-brand)); }
    .rowad-ejar-ready__label { font-size: .8rem; font-weight: 700; color: var(--rowad-ink); }
    .rowad-ejar-ready ul { margin: 0; padding-inline-start: 18px; display: grid; gap: 3px; font-size: .78rem; color: #b45309; }
    .rowad-ejar-ready li.is-more { color: var(--rowad-muted); list-style: none; }

    /* ---------- getting-started checklist ---------- */
    .rowad-start { background: #fff; border: 1px solid var(--rowad-line); border-radius: 20px; box-shadow: var(--rowad-shadow); padding: 20px 24px; }
    .rowad-start__head { display: grid; grid-template-columns: 1fr auto; gap: 8px 24px; align-items: center; margin-bottom: 14px; }
    .rowad-start__kicker { margin: 0; font-size: .74rem; font-weight: 700; color: var(--rowad-brand); letter-spacing: .04em; }
    .rowad-start__title { margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--rowad-ink); }
    .rowad-start__sub { margin: 2px 0 0; font-size: .86rem; color: var(--rowad-muted); }
    .rowad-start__meter { grid-column: 1; height: 8px; border-radius: 999px; background: var(--rowad-surface); overflow: hidden; }
    .rowad-start__meter span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--rowad-brand-light), var(--rowad-brand)); transition: width .4s ease; }
    .rowad-start__dismiss { grid-column: 2; grid-row: 1 / span 2; align-self: start; font: inherit; font-size: .8rem; font-weight: 700; color: var(--rowad-muted); background: none; border: 1px solid var(--rowad-line); border-radius: 999px; padding: 6px 12px; cursor: pointer; }
    .rowad-start__dismiss:hover { color: var(--rowad-ink); background: var(--rowad-surface); }
    .rowad-start__list { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px; }
    .rowad-start__step { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--rowad-line); background: #fbfcfc; min-width: 0; }
    .rowad-start__step.is-done { background: var(--rowad-brand-soft); border-color: var(--rowad-brand-line); }
    .rowad-start__num { width: 30px; height: 30px; flex: none; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: .85rem; color: var(--rowad-brand-dark); background: #fff; border: 1px solid var(--rowad-brand-line); }
    .rowad-start__step.is-done .rowad-start__num { background: var(--rowad-brand); color: #fff; border-color: transparent; }
    .rowad-start__num svg { width: 15px; height: 15px; }
    .rowad-start__text { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.35; }
    .rowad-start__text b { font-size: .9rem; font-weight: 700; color: var(--rowad-ink); }
    .rowad-start__text small { font-size: .74rem; color: var(--rowad-muted); }
    .rowad-start__step.is-done .rowad-start__text b { text-decoration: line-through; color: var(--rowad-muted); }
    .rowad-start__go { flex: none; font-size: .78rem; font-weight: 800; color: #fff; background: var(--rowad-brand); border-radius: 999px; padding: 5px 12px; }
    .rowad-start__go:hover { background: var(--rowad-brand-dark); }
    @media (max-width: 640px) { .rowad-start { padding: 16px; } .rowad-start__head { grid-template-columns: 1fr; } .rowad-start__dismiss { grid-column: 1; grid-row: auto; justify-self: start; } }

    /* ---------- record header card (view pages) ---------- */
    .rowad-record { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px 24px; padding: 20px 24px; border-radius: 20px;
        background: #fff; border: 1px solid var(--rowad-line); box-shadow: var(--rowad-shadow); border-inline-start: 5px solid var(--rowad-brand); }
    .rowad-record__main { display: flex; align-items: center; gap: 16px; min-width: 0; }
    .rowad-record__icon { width: 52px; height: 52px; flex: none; border-radius: 16px; display: grid; place-items: center; color: #fff;
        background: linear-gradient(135deg, var(--rowad-brand-light), var(--rowad-brand)); box-shadow: 0 8px 18px color-mix(in oklab, var(--rowad-brand) 30%, transparent); }
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
    /* platform console: health rows and status list */
    .rowad-health { display: flex; flex-direction: column; }
    .rowad-health__row { display: grid; grid-template-columns: 28px 220px 1fr; gap: 12px; align-items: baseline; padding: 10px 4px; border-bottom: 1px solid var(--rowad-line); font-size: .9rem; }
    .rowad-health__row:last-child { border-bottom: 0; }
    .rowad-health__mark { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 50%; font-weight: 800; font-size: .8rem; }
    .rowad-health__row.is-ok .rowad-health__mark { background: #dcfce7; color: #166534; }
    .rowad-health__row.is-warn .rowad-health__mark { background: #fef3c7; color: #92400e; }
    .rowad-health__row.is-fail .rowad-health__mark { background: #fee2e2; color: #991b1b; }
    .rowad-health__row.is-fail b { color: #991b1b; }
    .rowad-health__detail { color: var(--rowad-muted); }
    @media (max-width: 640px) { .rowad-health__row { grid-template-columns: 28px 1fr; } .rowad-health__detail { grid-column: 2; } }
    .rowad-status { display: grid; gap: 8px; margin: 0; }
    .rowad-status div { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--rowad-line); font-size: .9rem; }
    .rowad-status dt { color: var(--rowad-muted); } .rowad-status dd { margin: 0; font-weight: 700; direction: ltr; unicode-bidi: plaintext; }
    .rowad-credit { text-align: center; font-size: 11px; color: var(--rowad-muted); padding: 12px 16px 14px; }
    .rowad-credit--compact { padding: 10px 0 0; }
    .rowad-credit a { color: var(--rowad-brand); font-weight: 600; }
    .rowad-credit a:hover { text-decoration: underline; }
    .rowad-plan-notice { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin: 16px 0 4px; padding: 12px 16px; border-radius: 14px; font-weight: 600; font-size: .92rem; border: 1px solid; }
    .rowad-plan-notice--warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .rowad-plan-notice--danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .rowad-plan-notice--info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .rowad-plan-notice__icon { width: 20px; height: 20px; flex: none; }
    .rowad-plan-notice a { margin-inline-start: auto; text-decoration: underline; font-weight: 700; }
    /* the assistant: a small floating button at the bottom left, its label slides out on hover */
    .rowad-ai-shortcut { position: fixed; left: 20px; bottom: 20px; z-index: 40; display: inline-flex; align-items: center; gap: 0; height: 52px; min-width: 52px; padding: 0 15px;
        border-radius: 999px; color: #fff; font-size: .88rem; font-weight: 700; white-space: nowrap; overflow: hidden; direction: rtl;
        background: linear-gradient(135deg, var(--rowad-brand-light), var(--rowad-brand)); border: 2px solid #fff;
        box-shadow: 0 10px 28px color-mix(in oklab, var(--rowad-brand) 40%, transparent), 0 2px 6px rgb(15 23 42 / .15); transition: gap .2s, padding .2s, filter .15s; }
    [dir="ltr"] .rowad-ai-shortcut { direction: ltr; }
    .rowad-ai-shortcut svg { width: 22px; height: 22px; flex: none; }
    .rowad-ai-shortcut span { max-width: 0; opacity: 0; transition: max-width .25s ease, opacity .2s; }
    .rowad-ai-shortcut:hover, .rowad-ai-shortcut:focus-visible { gap: 8px; padding-inline: 16px 18px; filter: brightness(1.05); }
    .rowad-ai-shortcut:hover span, .rowad-ai-shortcut:focus-visible span { max-width: 12rem; opacity: 1; }
    @media (max-width: 48rem) { .rowad-ai-shortcut { left: 14px; bottom: calc(84px + env(safe-area-inset-bottom)); height: 48px; min-width: 48px; padding: 0 13px; } }
    @media print { .rowad-ai-shortcut { display: none !important; } }
    body.rowad-pdf-capture .rowad-ai-shortcut { display: none !important; }
</style>
<script>
    (() => {
        if (window.rowadPageChrome) return;
        const norm = (t) => (t || '').toLowerCase().replace(/[ً-ْـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').trim();

        // the page's breadcrumbs and title go to the topbar; its header gets the section's icon from the menu
        window.rowadPageChrome = () => {
            const header = document.querySelector('.fi-main .fi-header');
            const heading = header?.querySelector('.fi-header-heading');
            const slot = document.querySelector('.rowad-top-title');
            if (slot) {
                const crumbs = header?.querySelector('.fi-breadcrumbs');
                slot.querySelector('.rowad-top-title__crumbs').replaceChildren(...(crumbs ? [crumbs.cloneNode(true)] : []));
                slot.querySelector('.rowad-top-title__name').textContent = heading ? heading.textContent.trim() : document.title.split(' - ')[0];
                document.body.classList.toggle('rowad-has-top-title', !! crumbs);
            }
            if (heading && ! heading.querySelector('.rowad-head-icon')) {
                const icon = document.querySelector('.fi-sidebar-item.fi-active > .fi-sidebar-item-btn > svg, .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-icon');
                if (icon) {
                    const box = document.createElement('span');
                    box.className = 'rowad-head-icon';
                    box.append(icon.cloneNode(true));
                    heading.prepend(box);
                }
            }
        };
        document.addEventListener('DOMContentLoaded', window.rowadPageChrome);
        document.addEventListener('livewire:navigated', window.rowadPageChrome);
        document.addEventListener('livewire:init', () => window.Livewire.hook('commit', ({ succeed }) => succeed(() => requestAnimationFrame(window.rowadPageChrome))));

        // PDF of a report as shown on screen (its filters bar left out), drawn by the browser like the document PDFs
        window.rowadPagePdf = async (selector, filename, landscape = false) => {
            if (! window.RowadPdf) {
                await new Promise((resolve, reject) => document.head.append(Object.assign(document.createElement('script'),
                    { src: @js(asset('vendor/pdf/rowad-pdf.js')), onload: resolve, onerror: reject })));
            }
            document.body.classList.add('rowad-pdf-capture');
            try {
                await window.RowadPdf.save(document.querySelector(selector) ?? document.querySelector('.fi-page-content'), filename, { landscape, margin: 8 });
            } finally {
                document.body.classList.remove('rowad-pdf-capture');
            }
        };

        // the full-height sidebar is pulled up by the top bar's real height (two rows when the record search is shown)
        const topbarHeight = () => {
            const bar = document.querySelector('.fi-topbar-ctn');
            if (! bar || bar.dataset.rowadMeasured) return;
            bar.dataset.rowadMeasured = '1';
            const set = () => document.documentElement.style.setProperty('--rowad-topbar-h', bar.offsetHeight + 'px');
            new ResizeObserver(set).observe(bar);
            set();
        };
        document.addEventListener('DOMContentLoaded', topbarHeight);
        document.addEventListener('livewire:navigated', topbarHeight);

        // "more below" hint at the foot of the sidebar menu, gone once the end of the menu is reached
        const navMore = () => {
            const nav = document.querySelector('.fi-sidebar-nav');
            if (! nav) return;
            let hint = nav.querySelector(':scope > .rowad-nav-more');
            if (! hint) {
                hint = document.createElement('div');
                hint.className = 'rowad-nav-more';
                hint.innerHTML = '<button type="button"><svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg><span></span></button>';
                hint.querySelector('span').textContent = @js(__('المزيد بالأسفل'));
                hint.querySelector('button').addEventListener('click', () => nav.scrollBy({ top: nav.clientHeight * 0.7, behavior: 'smooth' }));
                nav.appendChild(hint);
                nav.addEventListener('scroll', () => update(), { passive: true });
                new ResizeObserver(() => update()).observe(nav);
            }
            const update = () => hint.classList.toggle('is-end', nav.scrollHeight - nav.scrollTop - nav.clientHeight < 24);
            update();
        };
        document.addEventListener('DOMContentLoaded', navMore);
        document.addEventListener('livewire:navigated', navMore);

        document.addEventListener('alpine:init', () => window.Alpine.data('rowadNavFilter', () => ({
            q: '',
            filter() {
                const needle = norm(this.q);
                const nav = document.querySelector('.fi-sidebar-nav');
                nav?.classList.toggle('rowad-nav-filtering', needle !== '');
                nav?.querySelectorAll('.fi-sidebar-group').forEach((group) => {
                    const groupHit = needle !== '' && norm(group.querySelector('.fi-sidebar-group-label')?.textContent).includes(needle);
                    let any = false;
                    group.querySelectorAll('.fi-sidebar-item').forEach((item) => {
                        const hit = needle === '' || groupHit || norm(item.textContent).includes(needle);
                        item.style.display = hit ? '' : 'none';
                        any ||= hit;
                    });
                    group.style.display = any ? '' : 'none';
                });
            },
            go() {
                [...document.querySelectorAll('.fi-sidebar-nav .fi-sidebar-item')].find((item) => item.style.display !== 'none')?.querySelector('a')?.click();
                this.q = '';
                this.filter();
            },
        })));
    })();
</script>
