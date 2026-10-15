{{-- Aqar Nova panel styling on top of Filament's defaults: softer surfaces, clearer hierarchy, Cairo throughout. --}}
<style>
    :root { --rowad-surface: #f5f7f8; --rowad-card: #ffffff; --rowad-line: #e8ecef; --rowad-ink: #0f172a; --rowad-muted: #64748b; --rowad-brand: #0f766e; --rowad-brand-dark: #0b5d57; }
    body.fi-body, .fi-layout { background: var(--rowad-surface); }
    body, .fi-body { font-feature-settings: "tnum" 1; }

    /* top bar & sidebar */
    .fi-topbar { box-shadow: 0 1px 0 var(--rowad-line); }
    .fi-sidebar-group-label { font-size: .72rem; letter-spacing: .02em; color: var(--rowad-muted); font-weight: 700; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background: rgb(15 118 110 / .08); }
    .fi-sidebar-item.fi-active .fi-sidebar-item-label { font-weight: 700; color: var(--rowad-brand-dark); }
    .fi-header-heading { font-weight: 800; letter-spacing: -.01em; }

    /* cards */
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat {
        border-radius: 16px !important; border: 1px solid var(--rowad-line) !important;
        box-shadow: 0 1px 2px rgb(15 23 42 / .04), 0 4px 16px rgb(15 23 42 / .04) !important;
    }
    .fi-section-header-heading { font-weight: 700; }
    .fi-section-header-description { color: var(--rowad-muted); }

    /* KPI cards */
    .fi-wi-stats-overview-stat { transition: transform .15s ease, box-shadow .15s ease; }
    .fi-wi-stats-overview-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgb(15 23 42 / .08) !important; }
    .fi-wi-stats-overview-stat-label { color: var(--rowad-muted); font-weight: 600; }
    .fi-wi-stats-overview-stat-value { font-weight: 800; letter-spacing: -.02em; color: var(--rowad-ink); }
    .fi-wi-stats-overview-stat-description { font-size: .8rem; }

    /* welcome banner */
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
</style>
<style>
    /* developer credit (Planova) */
    .rowad-credit { text-align: center; font-size: .8rem; color: var(--rowad-muted); padding: 18px 16px 22px; }
    .rowad-credit--compact { padding: 14px 0 0; }
    .rowad-credit a { color: var(--rowad-brand); font-weight: 700; }
    .rowad-credit a:hover { text-decoration: underline; }
</style>
