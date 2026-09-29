/**
 * Regression test for what a FORM shows (DesignChoices OK-20, OK-21 and OK-23).
 *
 * Run via `test/run-regression.sh form-groups` (the runner prepares the backend
 * API; this spec builds the Angular frontend into html/ itself), or against the
 * dev stack: node test/projects/form-groups/e2e/test.mjs
 *
 * The script:
 * 1. builds the frontend (compiler-generated sources + npm build) into html/;
 * 2. opens requirement R1 and asserts which fields and groups appear: filled fields
 *    and groups do, an empty read-only field does not, an empty editable field does,
 *    an empty group does not, and an empty group with showOnNoRecords does, with a dash;
 * 3. asserts the kind of field (group, meta) and the item as title;
 * 4. asserts that showSubOnNoRecords shows an empty read-only field;
 * 5. asserts that the New menu leaves out an API with create rights.
 */
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
try {
  require.resolve('puppeteer');
} catch {
  console.error('❌ Puppeteer is missing: run `npm install` in test/ first.');
  process.exit(1);
}
const puppeteer = (await import('puppeteer')).default;

const specDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(specDir, '../../../..');
const baseUrl = process.env.PROTOTYPE_URL ?? 'http://localhost';
const container = process.env.PROTOTYPE_CONTAINER ?? 'prototype';

let failures = 0;
function assert(cond, msg) {
  if (cond) {
    console.log(`  ✅ ${msg}`);
  } else {
    console.error(`  ❌ ${msg}`);
    failures++;
  }
}

function run(cmd, opts = {}) {
  execSync(cmd, { stdio: 'inherit', cwd: repoRoot, ...opts });
}

function buildFrontend() {
  console.log('▶ Building the frontend (compiler sources + npm build) ...');
  run(
    `docker exec ${container} sh -c "ampersand proto --frontend-version Angular --no-backend ` +
      `/var/www/test/projects/form-groups/model/main.adl ` +
      `--proto-dir /var/www/frontend/src/app/generated --crud-defaults cRud"`,
  );
  run('npm install --no-audit --no-fund', { cwd: resolve(repoRoot, 'frontend') });
  run('npm run build:dev', { cwd: resolve(repoRoot, 'frontend') });
  run('cp -r frontend/dist/prototype-frontend/. html/');
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function open(page, path) {
  await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle0', timeout: 30000 });
  await page.waitForSelector('app-box-form', { timeout: 15000 });
  // The FORM reads the field metadata from interfaces.json after the first render.
  await sleep(1000);
}

/** The label of every field that appears, with the kind the FORM gave it. */
function readFields(page) {
  return page.$$eval('.box-form-field', (els) =>
    els.map((e) => ({
      label: e.querySelector(':scope > .box-form-label')?.childNodes[0]?.textContent.trim() ?? '',
      kind: [...e.classList].find((c) => c.startsWith('box-form-field--'))?.slice(16) ?? '',
      count: e.querySelector(':scope > .box-form-label .box-form-count')?.textContent.trim() ?? '',
    })),
  );
}

buildFrontend();

// 'shell' renders without a display, also while the screen of a Mac is locked (see box-facets).
const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1400, height: 1000 });
const errors = [];
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
page.on('console', (m) => m.type() === 'error' && errors.push(`console: ${m.text()}`));

try {
  console.log('\n▶ Requirement R1: the fields a record has, and the fields the user may fill');
  await open(page, '/requirement/R1');
  const fields = await readFields(page);
  const by = Object.fromEntries(fields.map((f) => [f.label, f]));
  const labels = fields.map((f) => f.label);
  for (const shown of ['Properties', 'Status', 'Owner', 'Content', 'Text', 'Mentioned by', 'Rules']) {
    assert(labels.includes(shown), `filled field or group "${shown}" appears`);
  }
  assert(!labels.includes('Reviewers'), 'the empty read-only field Reviewers stays out');
  assert(labels.includes('Note'), 'the empty editable field Note appears, so it can be filled');
  assert(!labels.includes('History'), 'the empty group History stays out');
  assert(labels.includes('Trace'), 'the empty group Trace appears, for it says showOnNoRecords');
  const dashes = await page.$$eval('.box-form-none', (els) => els.map((e) => e.textContent.trim()));
  assert(dashes.includes('—'), 'the empty group Trace shows a dash instead of a blank card');

  console.log('\n▶ The kind of a field, and the item as title');
  assert(by['Properties']?.kind === 'group', `a FORM box on I is a group (got: ${by['Properties']?.kind})`);
  assert(by['Status']?.kind === 'meta', `a UNI field with a short value is meta (got: ${by['Status']?.kind})`);
  assert(by['Rules']?.count === '2', `a field with two values shows their number (got: ${by['Rules']?.count})`);
  const title = await page.$eval('app-interface-heading h2', (e) => e.textContent.trim()).catch(() => '');
  const eyebrow = await page.$eval('app-interface-heading h3', (e) => e.textContent.trim()).catch(() => '');
  assert(title === 'R1', `the title is the item (got: ${title})`);
  assert(eyebrow === 'Requirement', `the interface label stands above it (got: ${eyebrow})`);

  console.log('\n▶ showSubOnNoRecords shows every field');
  await open(page, '/requirementall/R1');
  const all = (await readFields(page)).map((f) => f.label);
  assert(all.includes('Reviewers'), 'the empty read-only field Reviewers appears under showSubOnNoRecords');

  console.log('\n▶ The New menu leaves out an API');
  const navbar = await (await fetch(`${baseUrl}/api/v1/app/navbar`)).json();
  const fresh = JSON.stringify(navbar.new ?? []);
  assert(!fresh.includes('NewRequirement'), `the API NewRequirement is no New item (got: ${fresh})`);

  assert(errors.length === 0, `no errors (got: ${errors.join(' | ') || 'none'})`);
} catch (e) {
  console.error(`  ❌ ${e.message}`);
  failures++;
} finally {
  await browser.close();
}

console.log(failures === 0 ? '\n✅ form-groups: all assertions passed' : `\n❌ form-groups: ${failures} failed`);
process.exit(failures === 0 ? 0 : 1);
