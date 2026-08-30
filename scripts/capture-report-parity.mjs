#!/usr/bin/env node

import fs from 'node:fs/promises';
import fsSync from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let playwright;
for (const candidate of [process.env.PLAYWRIGHT_MODULE, 'playwright', '/Users/king_developer/.npm/_npx/9833c18b2d85bc59/node_modules/playwright'].filter(Boolean)) {
  try { playwright = require(candidate); break; } catch {}
}
if (!playwright) throw new Error('Playwright is required; set PLAYWRIGHT_MODULE when it is not project-local');
const password = process.env.WP00C_TEST_PASSWORD;
const parityBootstrap = process.env.PARITY_SESSION_BOOTSTRAP === 'enabled';
if (!password && !parityBootstrap) {
  throw new Error('WP00C_TEST_PASSWORD is required unless PARITY_SESSION_BOOTSTRAP=enabled');
}

const output = path.resolve('evidence/strict-parity/report');
await fs.mkdir(output, { recursive: true });
const launchOptions = { headless: true };
const executable = [
  process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,
  '/Users/king_developer/Library/Caches/ms-playwright/chromium_headless_shell-1194/chrome-mac/headless_shell',
].filter(Boolean).find(candidate => fsSync.existsSync(candidate));
if (executable) launchOptions.executablePath = executable;
const browser = await playwright.chromium.launch(launchOptions);
const targets = [
  { id: 'ci3', base: 'http://127.0.0.1:18404', username: 'wp00c-parity-ci3', bootstrap: '/login?parity_session=admin' },
  { id: 'ci4', base: 'http://127.0.0.1:18405', username: 'wp00c-parity-ci4', bootstrap: '/__parity/session/admin' },
];
const sessions = new Map();
const interactions = [];
try {
  await Promise.all(targets.map(async target => {
    const context = await browser.newContext({
      viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1,
      locale: 'en-US', timezoneId: 'Asia/Bangkok', reducedMotion: 'reduce',
    });
    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    const failedRequests = [];
    const badResponses = [];
    page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('requestfailed', request => failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? '' }));
    page.on('response', response => { if (response.status() >= 400) badResponses.push({ url: response.url(), status: response.status() }); });
    if (parityBootstrap) {
      await page.goto(`${target.base}${target.bootstrap}`, { waitUntil: 'networkidle' });
    } else {
      await page.goto(`${target.base}/login`, { waitUntil: 'networkidle' });
      await page.locator('input[name="username"]').fill(target.username);
      await page.locator('input[name="password"]').fill(password);
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
        page.locator('button[type="submit"], input[type="submit"]').first().click(),
      ]);
    }
    if (!page.url().includes('/dashboard')) {
      const body = (await page.locator('body').innerText()).replace(/\s+/g, ' ').slice(0, 400);
      throw new Error(`${target.id} login failed at ${page.url()}: ${body}`);
    }
    await page.goto(`${target.base}/user/report`, { waitUntil: 'networkidle' });
    await page.locator('input[name="start_date"]').fill('01/01/2099');
    await page.locator('input[name="end_date"]').fill('02/01/2099');
    if (await page.locator('select[name="branch_id"]').count()) await page.locator('select[name="branch_id"]').selectOption('0');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle', timeout: 15000 }),
      page.locator('form#searchList button[type="submit"]').click(),
    ]);
    const facts = await page.evaluate(() => ({
      action: document.querySelector('form#searchList')?.getAttribute('action'),
      start: document.querySelector('input[name="start_date"]')?.value,
      end: document.querySelector('input[name="end_date"]')?.value,
      exportPath: new URL(document.querySelector('a[target="_blank"]')?.href ?? location.href).pathname,
      panels: document.querySelectorAll('.x_panel.tile.fixed_height_320').length,
      commentsTable: document.querySelectorAll('table#examples').length,
    }));
    Object.assign(facts, { consoleErrors, pageErrors, failedRequests, badResponses });
    if (facts.start !== '01/01/2099' || facts.end !== '02/01/2099' || facts.panels !== 9 || facts.commentsTable !== 1
        || consoleErrors.length || pageErrors.length || failedRequests.length || badResponses.length) {
      const body = (await page.locator('body').innerText()).replace(/\s+/g, ' ').slice(0, 400);
      throw new Error(`${target.id} report caller contract failed at ${page.url()}: ${JSON.stringify(facts)} body=${body}`);
    }
    sessions.set(target.id, { context, page });
    interactions.push({ target: target.id, ...facts });
  }));

  for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
    for (const target of targets) {
      const { page } = sessions.get(target.id);
      await page.setViewportSize(viewport);
      const before = await page.locator('body').getAttribute('class');
      await page.locator('a.sidebar-toggle').click();
      await page.waitForTimeout(150);
      const after = await page.locator('body').getAttribute('class');
      if (before === after) throw new Error(`${target.id} sidebar interaction failed`);
      await page.locator('a.sidebar-toggle').click();
      await page.waitForTimeout(150);
      await page.screenshot({ path: path.join(output, `report__${target.id}__${viewport.width}x${viewport.height}.png`), fullPage: true });
      if (viewport.width === 1440) await fs.writeFile(path.join(output, `report__${target.id}.html`), await page.content());
    }
  }
  await fs.writeFile(path.join(output, 'interaction.json'), JSON.stringify({ status: 'PASS', results: interactions }, null, 2) + '\n');
} finally {
  for (const session of sessions.values()) await session.context.close();
  await browser.close();
}
