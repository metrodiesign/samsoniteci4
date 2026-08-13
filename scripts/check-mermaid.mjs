// ตรวจทุก Mermaid block ในไฟล์ Markdown ด้วย parser จริง
// Usage: node scripts/check-mermaid.mjs <file.md> [...]
import { execFileSync } from 'node:child_process';
import { readFileSync, readdirSync, realpathSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { strict as assert } from 'node:assert';

// หา Mermaid ที่ติดมากับ mmdc เพราะ `npm root -g` อาจชี้ Node installation คนละชุด
const mmdcPath = execFileSync('which', ['mmdc'], { encoding: 'utf8' }).trim();
const cliDir = dirname(dirname(realpathSync(mmdcPath)));
const mermaidDist = join(cliDir, 'node_modules/mermaid/dist');
const { default: mermaid } = await import(join(mermaidDist, 'mermaid.esm.mjs'));

let flowDiagram;

async function parseFlowchart(source) {
  if (!flowDiagram) {
    const chunkDir = join(mermaidDist, 'chunks/mermaid.esm');
    const chunkName = readdirSync(chunkDir).find((name) => /^flowDiagram-.*\.mjs$/.test(name));

    if (!chunkName) {
      throw new Error('ไม่พบ Mermaid flowchart parser ใน mmdc installation');
    }

    ({ diagram: flowDiagram } = await import(join(chunkDir, chunkName)));
  }

  const db = flowDiagram.db;

  // งานนี้ parse syntax เท่านั้นและไม่ render HTML จึงไม่ต้องใช้ browser DOM สำหรับ DOMPurify
  db.sanitizeText = (text) => text;
  db.setAccTitle = () => {};
  db.setAccDescription = () => {};
  db.setDiagramTitle = () => {};
  flowDiagram.parser.parser.yy = db;
  db.clear?.();

  await flowDiagram.parser.parse(source);
}

async function parseDiagram(source) {
  if (/^\s*(?:flowchart|graph)\b/.test(source)) {
    await parseFlowchart(source);
    return;
  }

  await mermaid.parse(source);
}

async function runSelfTest() {
  await parseFlowchart('flowchart LR\n    A["Label"] --> B{"Decision?"}');
  await assert.rejects(() => parseFlowchart('flowchart LR\n    A -->'));
  console.log('OK   self-test: labeled flowchart ผ่าน และ invalid flowchart ถูกปฏิเสธ');
}

const files = process.argv.slice(2);

if (files.length === 1 && files[0] === '--self-test') {
  await runSelfTest();
  process.exit(0);
}

if (!files.length) {
  console.error('Usage: node scripts/check-mermaid.mjs <file.md> [...]');
  process.exit(2);
}

let failed = 0;

for (const file of files) {
  const source = readFileSync(file, 'utf8');
  const blocks = [...source.matchAll(/^```mermaid\n([\s\S]*?)^```/gm)];

  for (const [index, match] of blocks.entries()) {
    const line = source.slice(0, match.index).split('\n').length;

    try {
      await parseDiagram(match[1]);
      console.log(`OK   ${file}:${line} block ${index + 1}`);
    } catch (error) {
      failed++;
      const message = String(error.message).split('\n').slice(0, 3).join(' | ');
      console.log(`FAIL ${file}:${line} block ${index + 1} — ${message}`);
    }
  }

  if (!blocks.length) {
    console.log(`--   ${file} ไม่มี Mermaid block`);
  }
}

process.exit(failed ? 1 : 0);
