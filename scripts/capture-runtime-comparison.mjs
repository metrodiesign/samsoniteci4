#!/usr/bin/env node

import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import fsSync from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { spawnSync } from 'node:child_process';

const require = createRequire(import.meta.url);
let playwright;
for (const candidate of [process.env.PLAYWRIGHT_MODULE, 'playwright', '/Users/king_developer/.npm/_npx/9833c18b2d85bc59/node_modules/playwright'].filter(Boolean)) {
  try { playwright = require(candidate); break; } catch {}
}
if (!playwright) throw new Error('Playwright is required');
const tracePath = process.argv[2];
if (!tracePath) throw new Error('usage: capture-runtime-comparison.mjs RUNTIME_TRACES_JSON');
const trace = JSON.parse(await fs.readFile(tracePath, 'utf8'));
const output = path.dirname(path.resolve(tracePath));
const executablePath = [
  process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,
  '/Users/king_developer/Library/Caches/ms-playwright/chromium_headless_shell-1194/chrome-mac/headless_shell',
].filter(Boolean).find(candidate => fsSync.existsSync(candidate));
const bases = { ci3: 'http://127.0.0.1:18404', ci4: 'http://127.0.0.1:18405' };
const viewports = [{ width: 1440, height: 900 }, { width: 390, height: 844 }];
const results = [];
const RESET_ACTIVATION = 'PARITYRESET2026';
const RESET_TOKEN = 'd'.repeat(64);
const RESET_REQUEST_ID = 'parity-browser-reset-2026';
let resetEmail = null;
let browserScenarioCount = 0;

function database(sql, schema = '') {
  const databaseName = schema || '"$MARIADB_DATABASE"';
  const result = spawnSync('docker', [
    'exec', '-i', 'samsonitetracking-ci4-migration-db-1', 'sh', '-c',
    `mariadb -N -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" ${databaseName}`,
  ], { input: sql, encoding: 'utf8' });
  if (result.status !== 0) throw new Error('synthetic reset fixture database operation failed');
  return result.stdout.trim();
}

function seedResetFixture() {
  resetEmail = database("SELECT email FROM tbl_users WHERE username='wp00c-admin' AND isDeleted=0 LIMIT 1;\n");
  if (!resetEmail || !resetEmail.endsWith('@example.invalid')) throw new Error('synthetic reset fixture user unavailable');
  const email = resetEmail.replaceAll("'", "''");
  const hash = crypto.createHash('sha256').update(RESET_TOKEN).digest('hex');
  database(`DELETE FROM tbl_reset_password WHERE activation_id='${RESET_ACTIVATION}';\nINSERT INTO tbl_reset_password (email, activation_id, createdDtm, agent, client_ip) VALUES ('${email}','${RESET_ACTIVATION}',NOW(),'Parity','127.0.0.1');\n`);
  database(`DELETE FROM ci4_password_reset_tokens WHERE request_id='${RESET_REQUEST_ID}';\nINSERT INTO ci4_password_reset_tokens (user_id,purpose,request_id,token_hash,created_at,expires_at,consumed_at,revoked_at) SELECT id,'password_reset','${RESET_REQUEST_ID}','${hash}',NOW(),DATE_ADD(NOW(), INTERVAL 30 MINUTE),NULL,NULL FROM ci4_users WHERE username='wp00c-admin' AND is_active=1 LIMIT 1;\n`, 'samsonite_ci4');
}

function cleanupResetFixture() {
  database(`DELETE FROM tbl_reset_password WHERE activation_id='${RESET_ACTIVATION}';\n`);
  database(`DELETE FROM ci4_password_reset_tokens WHERE request_id='${RESET_REQUEST_ID}';\n`, 'samsonite_ci4');
}

function cleanupImportFixtures() {
  const fixtureRoot = path.join(output, 'upload-previews', 'fixtures');
  const hashes = ['status', 'price', 'new-order'].map(kind =>
    crypto.createHash('sha256').update(fsSync.readFileSync(path.join(fixtureRoot, `${kind}.xlsx`))).digest('hex'));
  const values = hashes.map(value => `'${value}'`).join(',');
  database(`DELETE FROM ci4_import_rows WHERE batch_id IN (SELECT batch_id FROM ci4_import_batches WHERE file_sha256 IN (${values}));\nDELETE FROM ci4_import_batches WHERE file_sha256 IN (${values});\n`, 'samsonite_ci4');
}

const localFontRoot = path.resolve('public/assets/fonts/source-sans-pro');
const localFontCss = (await fs.readFile(path.join(localFontRoot, 'stylesheet.css'), 'utf8'))
  .replace(/url\('([^']+)'\)/g, "url('https://parity-font.invalid/$1')");
let browser;
try {
  browser = await playwright.chromium.launch(executablePath ? { executablePath } : {});
} catch (error) {
  const blocked = {
    schema_version: 1, run_id: trace.run_id, timestamp: new Date().toISOString(), status: 'BLOCKED',
    reason: 'Chromium process could not start in current sandbox', results: [],
  };
  const blockedPath = path.join(output, 'automated-browser-results.json');
  await fs.writeFile(blockedPath, JSON.stringify(blocked, null, 2) + '\n');
  console.log(JSON.stringify({ status: 'BLOCKED', evidence: blockedPath }));
  process.exit(1);
}

async function settle(page) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(500);
}

async function interact(page, scenarioId) {
  if (scenarioId.startsWith('export-')) {
    const marker = scenarioId === 'export-ratings' ? 'excel_ratings'
      : scenarioId === 'export-progress' ? 'excel_in_progress_job'
      : 'excel_report';
    const link = page.locator(`a[href*="${marker}"]`).first();
    if (await link.count() !== 1) return { status: 'FAIL', reason: `export link ${marker} missing` };
    await link.evaluate(element => element.removeAttribute('target'));
    try {
      const downloadPromise = page.waitForEvent('download', { timeout: 10000 });
      await link.click();
      const download = await downloadPromise;
      return { status: 'PASS', action: 'click export link', suggested_filename: download.suggestedFilename() };
    } catch (error) {
      return { status: 'FAIL', action: 'click export link', reason: 'download event not observed' };
    }
  }
  const modalButton = page.locator('#btnModal, [data-target="#myModal"]').first();
  if (await modalButton.count() === 1) {
    await modalButton.click();
    await page.waitForTimeout(300);
    const state = await page.locator('#myModal, dialog').first().evaluate(element => ({
      display: getComputedStyle(element).display,
      open: element.hasAttribute('open') || element.classList.contains('in'),
    }));
    const visible = state.display !== 'none' && state.open;
    return { status: visible ? 'PASS' : 'FAIL', action: 'open modal', visible, state };
  }
  if (scenarioId.startsWith('framework-') || scenarioId === 'print-order') {
    return { status: 'NOT_APPLICABLE', reason: 'rendered output has no in-page interaction' };
  }
  const dataTableSearch = page.locator('input[type="search"]').first();
  if (await dataTableSearch.count() === 1) {
    await dataTableSearch.fill('PARITY-NO-MATCH');
    await page.waitForTimeout(100);
    return { status: 'PASS', action: 'DataTables search', value: 'PARITY-NO-MATCH' };
  }
  const search = page.locator('input[name="searchText"], input[name="search"]').first();
  if (await search.count() === 1) {
    await search.fill('PARITY-NO-MATCH');
    const form = search.locator('xpath=ancestor::form[1]');
    const submit = form.locator('button[type="submit"], input[type="submit"], .searchList').first();
    if (await submit.count() !== 1) return { status: 'FAIL', reason: 'search submit control missing' };
    await submit.click();
    await settle(page);
    return { status: 'PASS', action: 'submit search', value: 'PARITY-NO-MATCH' };
  }
  const reportDate = page.locator('input[name="start_date"], input[name="sdate"]').first();
  if (await reportDate.count() === 1) {
    await reportDate.fill('01/08/2026');
    const endDate = page.locator('input[name="end_date"], input[name="edate"]').first();
    if (await endDate.count() === 1) await endDate.fill('30/08/2026');
    const form = reportDate.locator('xpath=ancestor::form[1]');
    const submit = form.locator('button, input[type="submit"], .searchList').first();
    if (await submit.count() === 1) {
      await submit.click();
      await settle(page);
      return { status: 'PASS', action: 'submit report date filters' };
    }
  }
  const form = page.locator('form').filter({ has: page.locator('input:not([type="hidden"]), textarea, select') }).first();
  if (await form.count() === 1) {
    const required = form.locator('input.required:not([type="hidden"]):not([type="file"]):not([disabled]):not([readonly]), textarea.required:not([disabled]):not([readonly]), select.required:not([disabled]), input[required]:not([type="hidden"]):not([type="file"]):not([disabled]):not([readonly]), textarea[required]:not([disabled]):not([readonly]), select[required]:not([disabled])');
    if (await required.count() > 0) {
      const field = required.first();
      const tag = await field.evaluate(element => element.tagName.toLowerCase());
      if (tag === 'select') await field.selectOption({ index: 0 }); else await field.fill('');
      const invalid = await required.evaluateAll(elements => elements.filter(element => !element.checkValidity() || element.value === '' || element.value === '0').length);
      let submit = form.locator('button[type="submit"], input[type="submit"]').first();
      if (await submit.count() !== 1) submit = page.locator('button[type="submit"], input[type="submit"], #send_order_new').first();
      if (await submit.count() !== 1) return { status: 'FAIL', reason: 'form submit control missing' };
      await submit.click();
      await settle(page);
      return { status: invalid > 0 ? 'PASS' : 'FAIL', action: 'clear required field and trigger validation', invalid_fields: invalid };
    }
    const fileInput = form.locator('input[type="file"]').first();
    if (await fileInput.count() === 1) {
      const accept = await fileInput.getAttribute('accept');
      if (accept?.includes('image')) {
        await fileInput.setInputFiles(path.resolve('public/assets/images/bg-contact.png'));
        const selected = await fileInput.evaluate(element => element.files?.length ?? 0);
        return { status: selected === 1 ? 'PASS' : 'FAIL', action: 'select synthetic image upload', selected };
      }
      return { status: 'NOT_APPLICABLE', reason: 'spreadsheet upload interaction covered by dedicated preview scenario' };
    }
  }
  const pagination = page.locator('.pagination a').first();
  if (await pagination.count() === 1) {
    await pagination.click();
    await settle(page);
    return { status: 'PASS', action: 'pagination' };
  }
  return { status: 'NOT_APPLICABLE', reason: 'no supported in-page interaction' };
}

try {
  seedResetFixture();
  const frameworkScenarios = ['404', 'db', 'exception', 'general', 'php'].map(kind => ({
    id: `framework-html-${kind}`, ci3: `/parityerrors/html${kind}`, ci4: `/__parity/error/${kind}`,
    role: 'anonymous', language: 'en', state: kind,
  }));
  const uploadScenarios = [
    { id: 'upload-preview-status', uploadKind: 'status', ci3: '/UploadexcelListing', ci4: '/UploadexcelListing' },
    { id: 'upload-preview-price', uploadKind: 'price', ci3: '/UploadexcelpriceListing', ci4: '/UploadexcelpriceListing' },
    { id: 'upload-preview-new-order', uploadKind: 'new-order', ci3: '/UploadneworderexcelListing', ci4: '/UploadneworderexcelListing' },
  ].map(row => ({ ...row, role: 'branch', language: 'en', state: 'synthetic-upload' }));
  const browserScenarios = [
    ...trace.probes,
    ...frameworkScenarios,
    ...uploadScenarios,
    {
      id: 'new-password-valid', role: 'anonymous', language: 'en', state: 'valid-reset-token', redactUrl: true,
      ci3: `/resetPasswordConfirmUser/${RESET_ACTIVATION}/${encodeURIComponent(resetEmail)}`,
      ci4: `/reset-password?token=${RESET_TOKEN}`,
    },
  ];
  browserScenarioCount = browserScenarios.length;
  for (const scenario of browserScenarios) {
    for (const viewport of viewports) {
      for (const side of ['ci3', 'ci4']) {
        const context = await browser.newContext({
          viewport, deviceScaleFactor: 1, locale: 'en-US', timezoneId: 'Asia/Bangkok', reducedMotion: 'reduce',
        });
        await context.route('https://fonts.googleapis.com/**', route => route.fulfill({
          status: 200, contentType: 'text/css', body: localFontCss,
          headers: { 'Access-Control-Allow-Origin': '*', 'Access-Control-Allow-Headers': '*' },
        }));
        await context.route('https://parity-font.invalid/**', async route => {
          const name = path.basename(new URL(route.request().url()).pathname);
          const fontPath = path.join(localFontRoot, name);
          if (!fontPath.startsWith(localFontRoot) || !fsSync.existsSync(fontPath)) return route.abort();
          return route.fulfill({
            status: 200, contentType: 'font/ttf', body: await fs.readFile(fontPath),
            headers: { 'Access-Control-Allow-Origin': '*', 'Access-Control-Allow-Headers': '*' },
          });
        });
        await context.route(/^http:\/\/127\.0\.0\.1:1840[45]\/(?:synthetic-|uploads\/)/, async route => {
          const pathname = new URL(route.request().url()).pathname;
          const fixtureName = pathname.includes('trackstatus') ? 'bg-rs-tracking.png'
            : pathname.includes('track') ? (pathname.includes('mobile') ? 'bg-tracking-mb.png' : 'bg-tracking.png')
            : 'bg-contact.png';
          return route.fulfill({
            status: 200, contentType: 'image/png', body: await fs.readFile(path.resolve('public/assets/images', fixtureName)),
          });
        });
        const page = await context.newPage();
        if (scenario.role === 'admin' || scenario.role === 'branch') {
          const profile = scenario.role === 'branch' ? 'branch' : 'admin';
          const bootstrap = side === 'ci3' ? `/login?parity_session=${profile}` : `/__parity/session/${profile}`;
          await page.goto(bases[side] + bootstrap, { waitUntil: 'networkidle', timeout: 20000 });
          if (!page.url().includes('/dashboard')) throw new Error(`${side} supported parity session bootstrap failed`);
        }
        const consoleErrors = [];
        const failedRequests = [];
        page.on('console', message => {
          if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) {
            consoleErrors.push({ type: message.type(), text: message.text() });
          }
        });
        const requiredResource = request => ['stylesheet', 'script', 'font', 'image'].includes(request.resourceType());
        page.on('requestfailed', request => {
          if (requiredResource(request)) failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? 'unknown' });
        });
        page.on('response', response => {
          if (response.status() >= 400 && requiredResource(response.request())) {
            failedRequests.push({ url: response.url(), error: `HTTP ${response.status()}` });
          }
        });
        const requestId = `pw_${trace.run_id.slice(0, 8)}_${side}_${scenario.id.replaceAll('-', '_')}_${viewport.width}`;
        await page.setExtraHTTPHeaders({ 'X-Parity-Request-ID': requestId });
        const exportHosts = {
          'export-ratings': '/user/report',
          'export-progress': '/user/report_in_progress_job',
          'export-tracking': '/ReportTrackingListing',
          'export-summary': '/reportsummary',
        };
        const requestedRoute = exportHosts[scenario.id] ?? scenario[side];
        const route = scenario.id === 'tracking-result-th' && side === 'ci3' ? '/track_th' : requestedRoute;
        let response = await page.goto(bases[side] + route, { waitUntil: 'networkidle', timeout: 20000 });
        if (scenario.id === 'tracking-result-th' && side === 'ci3') {
          await page.locator('input[name="searchText"]').fill('PARITY-NOT-FOUND');
          const navigation = page.waitForNavigation({ waitUntil: 'networkidle' });
          await page.locator('input[type="submit"]').click();
          response = await navigation;
        }
        await page.locator('body').waitFor({ state: 'visible', timeout: 10000 });
        let interaction;
        if (scenario.uploadKind) {
          const fixture = path.join(output, 'upload-previews', 'fixtures', `${scenario.uploadKind}.xlsx`);
          const input = page.locator('input[type="file"]').first();
          if (await input.count() !== 1) {
            interaction = { status: 'FAIL', reason: 'upload input missing' };
          } else {
            await input.setInputFiles(fixture);
            const submit = page.locator('button[type="submit"], input[type="submit"]').first();
            await submit.click();
            await page.waitForLoadState('networkidle').catch(() => {});
            interaction = { status: 'PASS', action: 'upload synthetic XLSX and render preview' };
          }
        } else {
          interaction = await interact(page, scenario.id);
        }
        const label = `${viewport.width}x${viewport.height}`;
        const screenshot = `${scenario.id}__${side}__${label}.png`;
        const image = await page.screenshot({ path: path.join(output, screenshot), fullPage: true, animations: 'disabled' });
        results.push({
          scenario_id: scenario.id, side, viewport: label, request_id: requestId,
          requested_url: scenario.redactUrl ? `${bases[side]}/__REDACTED_RESET_PATH__` : bases[side] + requestedRoute,
          final_url: scenario.redactUrl ? `${bases[side]}/__REDACTED_RESET_PATH__` : page.url(), title: await page.title(),
          http_status: response?.status() ?? null, content_type: response?.headers()['content-type'] ?? null,
          interaction, console_errors: consoleErrors, failed_requests: failedRequests,
          screenshot, screenshot_sha256: crypto.createHash('sha256').update(image).digest('hex'),
        });
        await context.close();
      }
    }
  }
} finally {
  if (resetEmail !== null) cleanupResetFixture();
  cleanupImportFixtures();
  await browser.close();
}

const manifest = {
  schema_version: 1, run_id: trace.run_id, timestamp: new Date().toISOString(),
  browser: 'playwright-chromium', viewport_count: viewports.length, results,
};
const resultPath = path.join(output, 'automated-browser-results.json');
await fs.writeFile(resultPath, JSON.stringify(manifest, null, 2) + '\n');
console.log(JSON.stringify({
  scenarios: browserScenarioCount, captures: results.length,
  interaction_failures: results.filter(row => row.interaction.status === 'FAIL').length,
  console_errors: results.reduce((sum, row) => sum + row.console_errors.length, 0),
  failed_requests: results.reduce((sum, row) => sum + row.failed_requests.length, 0),
  evidence: resultPath,
}));
if (results.some(row => row.interaction.status === 'FAIL' || row.console_errors.length || row.failed_requests.length)) process.exitCode = 1;
