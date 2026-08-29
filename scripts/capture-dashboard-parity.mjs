#!/usr/bin/env node

import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const playwright = require('/Users/king_developer/.npm/_npx/9833c18b2d85bc59/node_modules/playwright');
const password = process.env.WP00C_TEST_PASSWORD;
if (!password) throw new Error('WP00C_TEST_PASSWORD is required');

const output = path.resolve('evidence/strict-parity/dashboard');
await fs.mkdir(output, { recursive: true });
const browser = await playwright.chromium.launch({
  headless: true,
  executablePath: '/Users/king_developer/Library/Caches/ms-playwright/chromium_headless_shell-1194/chrome-mac/headless_shell',
});

const targets = [
  { id: 'ci3', base: 'http://127.0.0.1:18404', username: 'wp00c-parity-ci3' },
  { id: 'ci4', base: 'http://127.0.0.1:18405', username: 'wp00c-parity-ci4' },
];
const sessions = new Map();
const results = [];
try {
  await Promise.all(targets.map(async (target) => {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      deviceScaleFactor: 1,
      locale: 'en-US',
      timezoneId: 'Asia/Bangkok',
    });
    const page = await context.newPage();
    await page.goto(`${target.base}/login`, { waitUntil: 'networkidle' });
    await page.locator('input[name="username"]').fill(target.username);
    await page.locator('input[name="password"]').fill(password);
    await Promise.all([
      page.waitForURL(/dashboard/, { timeout: 15000 }),
      page.locator('button[type="submit"], input[type="submit"]').first().click(),
    ]);
    if (!page.url().includes('/dashboard')) throw new Error(`${target.id} login did not reach dashboard`);
    sessions.set(target.id, { context, page });
  }));

  for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
    for (const target of targets) {
      const { page } = sessions.get(target.id);
      await page.setViewportSize(viewport);
      await page.goto(`${target.base}/dashboard`, { waitUntil: 'networkidle' });
      const status = await page.locator('body').count();
      if (status !== 1) throw new Error(`${target.id} dashboard has no body`);
      const before = await page.locator('body').getAttribute('class');
      if (await page.locator('a.sidebar-toggle').count() !== 1) {
        const errorText = (await page.locator('body').innerText()).replace(/\s+/g, ' ').slice(0, 500);
        throw new Error(`${target.id} dashboard sidebar toggle missing at ${page.url()} title=${await page.title()} bodyClass=${before} body=${errorText}`);
      }
      await page.locator('a.sidebar-toggle').click();
      await page.waitForTimeout(250);
      const after = await page.locator('body').getAttribute('class');
      if (before === after) {
        const runtime = await page.evaluate(() => ({ jquery: typeof window.jQuery, adminlte: typeof window.AdminLTE }));
        throw new Error(`${target.id} sidebar toggle did not change body class runtime=${JSON.stringify(runtime)}`);
      }
      await page.locator('a.sidebar-toggle').click();
      await page.waitForTimeout(250);
      await page.screenshot({
        path: path.join(output, `dashboard__${target.id}__${viewport.width}x${viewport.height}.png`),
        fullPage: true,
      });
      if (viewport.width === 1440) {
        await fs.writeFile(path.join(output, `dashboard__${target.id}.html`), await page.content());
      }
      results.push({ target: target.id, viewport: `${viewport.width}x${viewport.height}`, sidebar: 'PASS' });
    }
  }
  for (const target of targets) {
    const { page } = sessions.get(target.id);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${target.base}/dashboard`, { waitUntil: 'networkidle' });
    await Promise.all([
      page.waitForURL(/login/, { timeout: 15000 }),
      page.locator('li.btn-flat > a').click(),
    ]);
    results.push({ target: target.id, viewport: '1440x900', logout: 'PASS' });
  }
  await fs.writeFile(path.join(output, 'interaction.json'), JSON.stringify({ status: 'PASS', results }, null, 2) + '\n');
} finally {
  for (const session of sessions.values()) await session.context.close();
  await browser.close();
}
