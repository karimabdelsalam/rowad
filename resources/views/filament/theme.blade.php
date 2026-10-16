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
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn, .fi-sidebar-item-btn.fi-active { background: color-mix(in oklab, var(--rowad-brand-light) 28%, transparent) !important; box-shadow: inset 0 0 0 1px color-mix(in oklab, var(--rowad-brand-light) 35%, transparent); }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label, .fi-sidebar-item-btn.fi-active .fi-sidebar-item-label { color: #fff !important; font-weight: 700; }
    .fi-sidebar-item.fi-active .fi-icon, .fi-sidebar-item-btn.fi-active .fi-icon, .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > svg { color: color-mix(in oklab, var(--rowad-brand-light) 70%, #fff) !important; }
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
    .rowad-credit { text-align: center; font-size: .8rem; color: var(--rowad-muted); padding: 18px 16px 22px; }
    .rowad-credit--compact { padding: 14px 0 0; }
    .rowad-credit a { color: var(--rowad-brand); font-weight: 700; }
    .rowad-credit a:hover { text-decoration: underline; }
    .rowad-plan-notice { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin: 16px 0 4px; padding: 12px 16px; border-radius: 14px; font-weight: 600; font-size: .92rem; border: 1px solid; }
    .rowad-plan-notice--warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .rowad-plan-notice--danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .rowad-plan-notice--info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .rowad-plan-notice__icon { width: 20px; height: 20px; flex: none; }
    .rowad-plan-notice a { margin-inline-start: auto; text-decoration: underline; font-weight: 700; }
    .rowad-ai-shortcut { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: .85rem; font-weight: 700; color: #fff;
        background: linear-gradient(135deg, var(--rowad-brand-light), var(--rowad-brand)); box-shadow: 0 4px 12px color-mix(in oklab, var(--rowad-brand) 30%, transparent); margin-inline-end: 6px; }
    .rowad-ai-shortcut:hover { filter: brightness(1.08); }
    .rowad-ai-shortcut svg { width: 18px; height: 18px; }
    @media (max-width: 640px) { .rowad-ai-shortcut span { display: none; } .rowad-ai-shortcut { padding: 8px; } }
</style>
