# Rowad — Working Rules

## Rule #1: Keep going without stopping
The owner asked for continuous, uninterrupted progress on the Laravel/SaaS rewrite.
- Do not pause to ask "should I continue?" or "want me to…?" between phases.
- Make reasonable decisions yourself, document them in the commit message or
  `saas/docs/decisions.md`, and move on to the next phase.
- Commit and push after each completed step so progress is never lost.
- Stop only for something truly destructive or a decision only the owner can make
  (e.g. payment provider contracts, production credentials).

## Rule #2: Show designed pages in the conversation
Whenever a page with visible design is built or changed (landing page, panels, portals,
print documents, login, emails), take a screenshot (Playwright/Chromium against a local
demo office) and send it to the owner in the chat with SendUserFile while working.
Script: `saas/scripts/screenshots.mjs`, demo data: `php artisan rowad:demo`.

## Project layout
- `/` (root) — legacy PHP system (Smarty, ezSQL), upgraded to run on PHP 8.4. Kept as reference.
- `docs/feature-inventory.md` — the parity contract: every feature of the legacy
  system that the rewrite must reproduce.
- `saas/` — the new Laravel 13 multi-tenant SaaS rewrite.

## Rewrite conventions (saas/)
- Laravel 13, PHP 8.3+, Filament for admin/portal UIs, Arabic-first (RTL) with English.
- Multi-tenancy: database-per-tenant (stancl/tenancy). Central DB holds tenants,
  domains, plans, subscriptions. Tenant DB holds all business data.
- Every legacy module in `docs/feature-inventory.md` must map to a tenant migration,
  a model, a policy and a UI resource before it is considered done.
- Money columns: `decimal(14,2)`. Dates: store Gregorian, display Hijri/Gregorian per setting.
- Run `php artisan test` before every push.
