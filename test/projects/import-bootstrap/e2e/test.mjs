/**
 * Regression test for import-bootstrap mode (DesignChoices OK-09).
 *
 * A prototype with `global.importMode` boots locked into the import screen:
 * the server answers 423 on everything except the app/admin endpoints, the UI
 * hides the navigation and redirects every route to the import screen.
 * Imports commit with deferred invariant checking; "Start checking" runs the
 * one-time full check — red keeps the app locked and shows the violations,
 * green unlocks it permanently.
 *
 * Run via `test/run-regression.sh import-bootstrap` (the runner prepares the
 * backend API; this spec builds the Angular frontend into html/ itself), or
 * against the dev stack: node test/projects/import-bootstrap/e2e/test.mjs
 *
 * The script:
 * 1. builds the frontend (compiler-generated sources + npm build) into html/;
 * 2. enables global.importMode in project.yaml and (re)installs: the app locks;
 * 3. asserts the API lock (423) and the UI lock (redirect to the import
 *    screen, no sidebar);
 * 4. imports a Person without a name through the UI: commits despite the
 *    violated TOT invariant;
 * 5. "Start checking" → red: violation shown, app stays locked;
 * 6. imports the name, "Start checking" → green: app unlocks, menu returns,
 *    routes answer normally again.
 */
import { execSync } from 'node:child_process';
import { existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { createRequire } from 'node:module';

// A fresh worktree has no test/node_modules yet (gitignored); install on demand
const require = createRequire(import.meta.url);
const testDir = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
try {
  require.resolve('puppeteer');
} catch {
  execSync('npm install --no-audit --no-fund', { cwd: testDir, stdio: 'inherit' });
}
const puppeteer = (await import('puppeteer')).default;

const specDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(specDir, '../../../..');
const projectYaml = resolve(repoRoot, 'backend/config/project.yaml');
const unlockFlag = resolve(repoRoot, 'data/importmode.unlocked');
const baseUrl = process.env.PROTOTYPE_URL ?? 'http://localhost';
// test/run-regression.sh runs this spec against its own stack; without it, the dev stack.
const container = process.env.PROTOTYPE_CONTAINER ?? 'prototype';

const originalYaml = readFileSync(projectYaml, 'utf8');

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

/* Build the Angular frontend into html/ (the regression runner only copies the
 * backend API there). Same recipe as generate.sh: compiler-generated sources,
 * npm build, copy the dist over html/. */
function buildFrontend() {
  console.log('Building the frontend (compiler sources + npm build) ...');
  run(
    `docker exec ${container} sh -c "ampersand proto --frontend-version Angular --no-backend ` +
      `/var/www/test/projects/import-bootstrap/model/main.adl ` +
      `--proto-dir /var/www/frontend/src/app/generated --crud-defaults cRud"`,
  );
  run('npm install --no-audit --no-fund', { cwd: resolve(repoRoot, 'frontend') });
  run('npm run build:dev', { cwd: resolve(repoRoot, 'frontend') });
  run('cp -r frontend/dist/prototype-frontend/. html/');
}

function setImportMode(on) {
  const content = on
    ? '# TEMPORARY test config, written by test/projects/import-bootstrap/e2e/test.mjs\nsettings:\n  global.importMode: true\n'
    : originalYaml;
  writeFileSync(projectYaml, content);

  // The macOS bind mount propagates file changes with a delay; wait until the
  // container sees the new settings before hitting the installer
  const deadline = Date.now() + 15000;
  for (;;) {
    const inContainer = execSync(
      `docker exec ${container} cat /var/www/backend/config/project.yaml`,
      { encoding: 'utf8' },
    );
    if (inContainer === content) {
      return;
    }
    if (Date.now() > deadline) {
      throw new Error('project.yaml did not propagate to the container');
    }
    execSync('sleep 0.2');
  }
}

async function api(path) {
  const resp = await fetch(`${baseUrl}/api/v1/${path}`);
  return resp;
}

async function getNavbar() {
  const resp = await api('app/navbar');
  if (!resp.ok) {
    throw new Error(`navbar failed: ${resp.status} ${await resp.text()}`);
  }
  return resp.json();
}

/* Upload a population file through the UI file input; the import screen
 * auto-starts the upload on select. Waits for the per-file done marker. */
async function uploadThroughUi(page, file) {
  const input = await page.waitForSelector('p-fileupload input[type=file]');
  await input.uploadFile(resolve(specDir, file));
  await page.waitForFunction(
    (name) =>
      [...document.querySelectorAll('.file-item .filename.done')].some((el) =>
        el.textContent.includes(name),
      ),
    { timeout: 30000 },
    file,
  );
}

async function main() {
  buildFrontend();

  console.log('Enabling import mode and (re)installing ...');
  setImportMode(true);
  const inst = await api('admin/installer?ignoreInvariantRules=true');
  if (!inst.ok) {
    throw new Error(`installer failed: ${inst.status} ${await inst.text()}`);
  }

  // --- API level: the lock is real, not only cosmetic
  let navbar = await getNavbar();
  assert(navbar.importMode === true, 'navbar exposes importMode');
  assert(navbar.appLocked === true, 'after (re)install the app is locked');
  const locked = await api('resource/Person');
  assert(locked.status === 423, `resource requests answer 423 while locked (got ${locked.status})`);

  // --- UI level
  const browser = await puppeteer.launch({ headless: true });
  try {
    const page = await browser.newPage();
    page.on('pageerror', (err) => console.error('  page error:', err.message));

    console.log('Opening the app while locked ...');
    await page.goto(baseUrl, { waitUntil: 'networkidle2' });
    await page.waitForFunction(
      () => window.location.pathname.startsWith('/admin/population/import'),
      { timeout: 15000 },
    );
    assert(true, 'boot redirects to the import screen');
    assert(
      (await page.$('.layout-sidebar')) === null,
      'the navigation sidebar is hidden while locked',
    );
    assert(
      (await page.$('.import-mode-panel')) !== null,
      'the import screen shows the import-mode panel',
    );

    console.log('Importing a Person without a name (violates the TOT invariant) ...');
    await uploadThroughUi(page, 'person-without-name.json');
    assert(true, 'the violating import commits (deferred checking)');

    console.log('Start checking → red ...');
    await page.click('.import-mode-panel button');
    await page.waitForSelector('.check-result', { timeout: 30000 });
    const resultText = await page.$eval('.check-result', (el) => el.textContent);
    assert(
      resultText.includes('cannot start'),
      'red: the cannot-start message is shown',
    );
    assert(
      resultText.includes('name[Person*Name]') || resultText.toLowerCase().includes('name'),
      'red: the violated invariant is shown',
    );
    navbar = await getNavbar();
    assert(navbar.appLocked === true, 'red: the app stays locked');

    console.log('Importing the name, then Start checking → green ...');
    await uploadThroughUi(page, 'names.json');
    await page.click('.import-mode-panel button');
    await page.waitForFunction(
      () => !window.location.pathname.startsWith('/admin/population/import'),
      { timeout: 30000 },
    );
    assert(true, 'green: the app navigates away from the import screen');
    await page.waitForSelector('.layout-sidebar', { timeout: 15000 });
    assert(true, 'green: the navigation sidebar returns');

    navbar = await getNavbar();
    assert(navbar.appLocked === false, 'green: the app is unlocked');
    const open = await api('resource/Person');
    assert(open.status !== 423, `resource requests answer normally again (got ${open.status})`);
  } finally {
    await browser.close();
  }
}

try {
  await main();
} catch (err) {
  console.error(`  ❌ ${err.message}`);
  failures++;
} finally {
  // Leave the working copy as found: original settings, no unlock flag
  writeFileSync(projectYaml, originalYaml);
  if (existsSync(unlockFlag)) {
    rmSync(unlockFlag);
  }
}

if (failures > 0) {
  console.error(`${failures} assertion(s) failed`);
  process.exit(1);
}
console.log('import-bootstrap: all assertions passed');
