// Screenshots of the demo office for review in the chat.
// Usage: node scripts/screenshots.mjs <out-dir> [page ...]   (server: php artisan serve --port=8000, data: php artisan rowad:demo)
import { createRequire } from 'node:module';

// local install, or a global one via NODE_PATH=$(npm root -g)
const { chromium } = createRequire(import.meta.url)('playwright');

const [outDir = 'screenshots', ...only] = process.argv.slice(2);
const host = process.env.DEMO_HOST ?? 'http://demo.localhost:8000';
const centralHost = process.env.CENTRAL_HOST ?? 'http://localhost:8000';
const password = 'demo-password';

const pages = [
  { name: 'platform-home', path: '/', central: true, full: true },
  { name: 'platform-home-top', path: '/', central: true },
  { name: 'platform-home-mobile', path: '/', central: true, mobile: true, full: true },
  { name: 'platform-signup', path: '/signup?plan=2', central: true, full: true },
  { name: 'site-home', path: '/', full: true },
  { name: 'site-unit', path: '/units/1', full: true },
  { name: 'site-home-mobile', path: '/', mobile: true, full: true },
  { name: 'site-home-mobile-top', path: '/', mobile: true },
  { name: 'admin-login', path: '/admin/login' },
  { name: 'admin-dashboard', path: '/admin', login: 'admin@demo.test', full: true },
  { name: 'admin-units', path: '/admin/units', login: 'admin@demo.test' },
  { name: 'admin-rent-contracts', path: '/admin/rent-contracts', login: 'admin@demo.test' },
  { name: 'admin-installments', path: '/admin/installments', login: 'admin@demo.test' },
  { name: 'admin-owner-transactions', path: '/admin/owner-transactions', login: 'admin@demo.test' },
  { name: 'admin-settings', path: '/admin/general-settings', login: 'admin@demo.test' },
  { name: 'owner-dashboard', path: '/owner', login: 'owner@demo.test', panel: 'owner', full: true },
  { name: 'owner-statement', path: '/owner/owner-transactions', login: 'owner@demo.test', panel: 'owner' },
  { name: 'owner-units', path: '/owner/units', login: 'owner@demo.test', panel: 'owner' },
  { name: 'owner-installments', path: '/owner/installments', login: 'owner@demo.test', panel: 'owner' },
  { name: 'owner-rent-contracts', path: '/owner/rent-contracts', login: 'owner@demo.test', panel: 'owner' },
  { name: 'owner-tickets', path: '/owner/support-tickets', login: 'owner@demo.test', panel: 'owner' },
  { name: 'client-installments', path: '/client/installments', login: 'client@demo.test', panel: 'client' },
  { name: 'client-rent-contracts', path: '/client/rent-contracts', login: 'client@demo.test', panel: 'client' },
  { name: 'client-units', path: '/client/units', login: 'client@demo.test', panel: 'client' },
  { name: 'client-dashboard', path: '/client', login: 'client@demo.test', panel: 'client', full: true },
  { name: 'print-rent-contract', path: '/print/rent_contract/1', login: 'admin@demo.test', full: true },
  { name: 'print-receipt', path: '/print/receipt_voucher/1', login: 'admin@demo.test', full: true },
  { name: 'print-qr-owner', path: '/print/qr/owner', login: 'admin@demo.test', full: true },
  { name: 'print-unit', path: '/print/unit/6', login: 'admin@demo.test', full: true },
  { name: 'print-letter', path: '/print/letter/1', login: 'admin@demo.test', full: true },
  { name: 'form-unit', path: '/admin/units/create', login: 'admin@demo.test', full: true },
  { name: 'form-rent-contract', path: '/admin/rent-contracts/create', login: 'admin@demo.test', full: true },
  { name: 'form-sale-contract', path: '/admin/sale-contracts/create', login: 'admin@demo.test', full: true },
  { name: 'form-receipt', path: '/admin/receipt-vouchers/create', login: 'admin@demo.test', full: true },
  { name: 'form-owner', path: '/admin/owners/create', login: 'admin@demo.test', full: true },
  { name: 'form-client', path: '/admin/clients/create', login: 'admin@demo.test', full: true },
  { name: 'form-employee', path: '/admin/employees/create', login: 'admin@demo.test', full: true },
  { name: 'view-unit', path: '/admin/units/6', login: 'admin@demo.test', full: true },
  { name: 'view-rent-contract', path: '/admin/rent-contracts/1', login: 'admin@demo.test', full: true },
  { name: 'view-sale-contract', path: '/admin/sale-contracts/1', login: 'admin@demo.test', full: true },
  { name: 'report-income', path: '/admin/reports/income-report', login: 'admin@demo.test', full: true },
  { name: 'report-rent', path: '/admin/reports/rent-report', login: 'admin@demo.test', full: true },
  { name: 'report-units', path: '/admin/reports/units-report', login: 'admin@demo.test', full: true },
  { name: 'report-profits', path: '/admin/reports/profits-report', login: 'admin@demo.test', full: true },
  { name: 'bank-transactions', path: '/admin/reports/banking-report?filters[bank_account_id][value]=1&filters[period][from]=2025-01-01&filters[period][until]=2026-12-31', login: 'admin@demo.test' },
  { name: 'settings-numbering', path: '/admin/numbering', login: 'admin@demo.test', full: true },
  { name: 'office-files', path: '/admin/office-files', login: 'admin@demo.test' },
  { name: 'settings-website', path: '/admin/website-settings', login: 'admin@demo.test', full: true },
];

const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP *.localhost 127.0.0.1'] });
const sessions = {};

async function context(page) {
  const key = (page.login ?? 'guest') + (page.mobile ? '-m' : '');
  if (sessions[key]) return sessions[key];
  const ctx = await browser.newContext(page.mobile
    ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, locale: 'ar-SA' }
    : { viewport: { width: 1440, height: 900 }, locale: 'ar-SA' });
  if (page.login) {
    const p = await ctx.newPage();
    await p.goto(`${host}/${page.panel ?? 'admin'}/login`, { waitUntil: 'networkidle' });
    await p.fill('input[id="form.email"], input[type="email"], input[autocomplete="username"]', page.login);
    await p.fill('input[type="password"]', password);
    await Promise.all([p.waitForURL(u => !u.pathname.endsWith('/login'), { timeout: 30000 }), p.keyboard.press('Enter')]);
    await p.close();
  }
  return (sessions[key] = ctx);
}

for (const page of pages.filter(p => only.length === 0 || only.includes(p.name))) {
  const ctx = await context(page);
  const p = await ctx.newPage();
  await p.goto((page.central ? centralHost : host) + page.path, { waitUntil: 'networkidle', timeout: 60000 });
  // lazy widgets load when scrolled into view
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 400) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 150)); } window.scrollTo(0, 0); });
  await p.waitForLoadState('networkidle');
  await p.waitForTimeout(1500);
  await p.screenshot({ path: `${outDir}/${page.name}.png`, fullPage: !!page.full });
  console.log(`${outDir}/${page.name}.png`);
  await p.close();
}
await browser.close();
