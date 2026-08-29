#!/usr/bin/env node

import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import fsSync from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let playwright;
for (const candidate of [process.env.PLAYWRIGHT_MODULE, 'playwright', '/Users/king_developer/.npm/_npx/9833c18b2d85bc59/node_modules/playwright'].filter(Boolean)) {
  try { playwright = require(candidate); break; } catch {}
}
if (!playwright) throw new Error('Playwright is required; set PLAYWRIGHT_MODULE when it is not project-local');
const root = process.cwd();
const resultPath = path.join(root, 'evidence/strict-parity/views/runtime-results.json');
const manifest = JSON.parse(await fs.readFile(resultPath, 'utf8'));
const launchOptions = { headless: true };
const executableCandidates = [
  process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE,
  '/Users/king_developer/Library/Caches/ms-playwright/chromium_headless_shell-1194/chrome-mac/headless_shell',
].filter(Boolean);
const executable = executableCandidates.find(candidate => fsSync.existsSync(candidate));
if (executable) launchOptions.executablePath = executable;
const browser = await playwright.chromium.launch(launchOptions);
const contexts = await Promise.all(['ci3', 'ci4'].map(() => browser.newContext({
  viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1,
  locale: 'en-US', timezoneId: 'Asia/Bangkok', reducedMotion: 'reduce',
})));
const pages = await Promise.all(contexts.map(context => context.newPage()));
const digest = buffer => crypto.createHash('sha256').update(buffer).digest('hex');
const failures = [];

function documentFor(kind, output) {
  if (kind === 'framework_cli') {
    return `<!doctype html><html><head><meta charset="utf-8"><style>body{margin:16px;background:#111;color:#eee}pre{white-space:pre-wrap;font:14px/1.4 monospace}</style></head><body><pre>${output.replaceAll('&', '&amp;').replaceAll('<', '&lt;')}</pre></body></html>`;
  }
  return output;
}

async function interaction(page, kind) {
  const facts = await page.evaluate(() => ({
    body: Boolean(document.body),
    text: document.body?.innerText.trim().length ?? 0,
    forms: [...document.forms].map(form => ({ action: form.getAttribute('action'), method: form.method })),
    tables: document.querySelectorAll('table').length,
    links: [...document.querySelectorAll('a[href]')].map(link => link.getAttribute('href')),
  }));
  if (!facts.body) throw new Error('document has no body');
  if (kind === 'email' && facts.links.some(link => link === null || link === '')) throw new Error('email has an empty link');
  if (kind === 'export' && facts.tables < 1) throw new Error('export has no table');
  if (facts.forms.some(form => !form.action || !form.method)) throw new Error('form caller contract is incomplete');
  return facts;
}

try {
  for (let index = 0; index < manifest.scenarios.length; index++) {
    const scenario = manifest.scenarios[index];
    const directory = path.dirname(path.join(root, scenario.outputs.ci3));
    const outputs = await Promise.all([
      fs.readFile(path.join(root, scenario.outputs.ci3), 'utf8'),
      fs.readFile(path.join(root, scenario.outputs.ci4), 'utf8'),
    ]);
    const documents = outputs.map(output => documentFor(scenario.kind, output));
    try {
      await Promise.all(pages.map((page, side) => page.setContent(documents[side], { waitUntil: 'load', timeout: 15000 })));
      const facts = await Promise.all(pages.map(page => interaction(page, scenario.kind)));
      if (JSON.stringify(facts[0]) !== JSON.stringify(facts[1])) throw new Error('interaction facts differ');
      const visual = {};
      for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
        await Promise.all(pages.map(page => page.setViewportSize(viewport)));
        const images = await Promise.all(pages.map(page => page.screenshot({ animations: 'disabled' })));
        const label = `${viewport.width}x${viewport.height}`;
        const names = [`ci3__${label}.png`, `ci4__${label}.png`];
        await Promise.all(images.map((image, side) => fs.writeFile(path.join(directory, names[side]), image)));
        const hashes = images.map(digest);
        visual[label] = { status: hashes[0] === hashes[1] ? 'PASS' : 'FAIL', ci3_sha256: hashes[0], ci4_sha256: hashes[1] };
        if (hashes[0] !== hashes[1]) throw new Error(`visual differs at ${label}`);
      }
      scenario.interaction = 'PASS';
      scenario.visual = 'PASS';
      scenario.interaction_evidence = facts[1];
      scenario.visual_evidence = visual;
    } catch (error) {
      scenario.interaction = 'FAIL';
      scenario.visual = 'FAIL';
      scenario.browser_error = String(error.message ?? error);
      failures.push(scenario.id);
    }
    if ((index + 1) % 10 === 0) process.stdout.write(`captured ${index + 1}/${manifest.scenarios.length}\n`);
  }
} finally {
  await Promise.all(contexts.map(context => context.close()));
  await browser.close();
}
manifest.summary = {
  runtime_pass: manifest.scenarios.filter(row => row.runtime === 'PASS').length,
  dom_pass: manifest.scenarios.filter(row => row.dom === 'PASS').length,
  interaction_pass: manifest.scenarios.filter(row => row.interaction === 'PASS').length,
  visual_pass: manifest.scenarios.filter(row => row.visual === 'PASS').length,
};
await fs.writeFile(resultPath, JSON.stringify(manifest, null, 2) + '\n');
console.log(JSON.stringify({ scenarios: manifest.scenario_count, ...manifest.summary, failures }));
if (failures.length) process.exitCode = 1;
