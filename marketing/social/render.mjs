// Renders every *.html in this folder to a PNG at its own size:  node render.mjs [outDir]
import { createRequire } from 'node:module';
import { readdirSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const { chromium } = createRequire(import.meta.url)('playwright');
const here = dirname(fileURLToPath(import.meta.url));
const out = resolve(process.argv[2] ?? resolve(here, 'out'));
mkdirSync(out, { recursive: true });
const b = await chromium.launch();
for (const file of readdirSync(here).filter((f) => f.endsWith('.html'))) {
  const [, w, h] = /width:(\d+)px;height:(\d+)px/.exec((await import('node:fs')).readFileSync(resolve(here, file), 'utf8')) ?? [];
  const p = await (await b.newContext({ viewport: { width: +w, height: +h }, deviceScaleFactor: 1 })).newPage();
  await p.goto('file://' + resolve(here, file)); await p.evaluate(() => document.fonts.ready); await p.waitForTimeout(300);
  await p.screenshot({ path: resolve(out, file.replace('.html', '.png')), clip: { x: 0, y: 0, width: +w, height: +h } });
  console.log(file, `${w}x${h}`);
}
await b.close();
