/**
 * Regression test for BOX<FACETS>: a table with a facet panel.
 *
 * Run via `test/run-regression.sh box-facets` (the runner prepares the backend
 * API; this spec builds the Angular frontend into html/ itself), or against the
 * dev stack: node test/projects/box-facets/e2e/test.mjs
 *
 * The script:
 * 1. builds the frontend (compiler-generated sources + npm build) into html/;
 * 2. checks that the build serves concepts.json, the source of the TTypes;
 * 3. opens the Tickets interface and asserts the kind of facet per TType, the
 *    counts, the recursion into the Team box, and that PASSWORD is no facet;
 * 4. filters through the panel, the search field, a range and the date tree,
 *    and asserts the rows, the counts and the query parameters;
 * 5. opens a URL with a selection in it (bookmark) and asserts the result;
 * 6. asserts `facets`/`facetOnly` (TicketsByTeam) and a FACETS box nested in
 *    a FORM (TeamDetail);
 * 7. asserts that a sort the user chose survives a facet click, and `facetKind`
 *    (TicketsReported);
 * 8. creates a row in a plain TABLE (NewTickets) and asserts it appears once.
 * Every error toast fails the spec. Step 8 adds a row, so a second run on the
 * same stack needs a fresh install first.
 */
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { createRequire } from 'node:module';

// A fresh worktree has no test/node_modules yet (gitignored). The spec does not install
// them itself: it states the one command that does.
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
// test/run-regression.sh runs this spec against its own stack; without it, the dev stack.
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
      `/var/www/test/projects/box-facets/model/main.adl ` +
      `--proto-dir /var/www/frontend/src/app/generated --crud-defaults cRud"`,
  );
  run('npm install --no-audit --no-fund', { cwd: resolve(repoRoot, 'frontend') });
  run('npm run build:dev', { cwd: resolve(repoRoot, 'frontend') });
  run('cp -r frontend/dist/prototype-frontend/. html/');
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// Every error toast fails the spec (a success toast such as "created" does not). A sortable table with sortBy used to raise NG0100 on
// `attr.aria-sort` in a development build (SortableColumnDirective); the sorted tables in
// this model guard that it no longer does.
const toasts = [];
async function collectToasts(page) {
  toasts.push(
    ...(await page.$$eval('.p-toast-message-error', (els) => els.map((e) => e.innerText.replace(/\s+/g, ' ')))),
  );
}

async function open(page, path) {
  if (page.url().startsWith('http')) await collectToasts(page);
  await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle0', timeout: 30000 });
  await page.waitForSelector('[data-testid=facets-count]', { timeout: 15000 });
  await sleep(500);
}

const countText = (page) => page.$eval('[data-testid=facets-count]', (e) => e.innerText.trim());

/** The facets in the panel: data-facet → { title, values: {label: count}, inputs } */
function readFacets(page) {
  return page.$$eval('.facet', (els) =>
    els.map((e) => ({
      id: e.dataset.facet,
      title: e.querySelector('.facet__title')?.innerText.trim(),
      values: Object.fromEntries(
        [...e.querySelectorAll('.facet__value')].filter((v) => v.closest('.facet') === e).map((v) => [
          v.querySelector('.facet__label').innerText.trim(),
          Number(v.querySelector('.facet__n').innerText),
        ]),
      ),
      inputs: [...e.querySelectorAll(':scope > input, :scope > .facet__range > input')].map(
        (i) => i.getAttribute('type') ?? 'text',
      ),
    })),
  );
}

async function clickValue(page, facetId, label) {
  const boxes = await page.$$(`.facet[data-facet="${facetId}"] .facet__value`);
  for (const b of boxes) {
    const text = await b.$eval('.facet__label', (e) => e.innerText.trim());
    if (text === label) {
      await (await b.$('input')).click();
      await sleep(400);
      return;
    }
  }
  throw new Error(`no value '${label}' in facet ${facetId}`);
}

const queryOf = (page) => new URL(page.url()).searchParams;

buildFrontend();

// 'shell' is the headless Chrome that renders without a display. The newer headless mode
// stops producing animation frames on macOS while the screen is locked (measured on
// 2026-09-28: 0 frames per second, even on about:blank), and every click then waits forever.
const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1400, height: 1000 });
const errors = [];
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
page.on('console', (m) => m.type() === 'error' && errors.push(`console: ${m.text()}`));

try {
  console.log('\n▶ concepts.json is served next to interfaces.json');
  const concepts = await (await fetch(`${baseUrl}/assets/concepts.json`)).text();
  assert(concepts.trim().startsWith('['), '/assets/concepts.json is JSON (not the SPA index)');

  console.log('\n▶ Tickets: one facet per item, its kind from the TType');
  await open(page, '/tickets');
  assert((await countText(page)) === '14 of 14', `count 14 of 14 (got: ${await countText(page)})`);
  const facets = Object.fromEntries((await readFacets(page)).map((f) => [f.id, f]));
  assert(facets.Ticket?.inputs.includes('search'), 'identity item Ticket is a text field');
  assert(
    JSON.stringify(facets.Status?.values) === JSON.stringify({ open: 7, closed: 4, '(empty)': 3 }),
    `ALPHANUMERIC Status lists values with counts (got: ${JSON.stringify(facets.Status?.values)})`,
  );
  assert(facets.Tags?.values.bug === 5, 'multi-valued Tags counts rows per value (bug: 5)');
  assert(facets.Summary?.inputs.includes('search'), 'BIGALPHANUMERIC Summary is a text field');
  assert(facets.Notes?.inputs.includes('search'), 'HUGEALPHANUMERIC Notes is a text field');
  assert(
    facets.Due?.values['2026'] === 8 && facets.Due?.values['2025'] === 2,
    `DATE Due groups by year (got: ${JSON.stringify(facets.Due?.values)})`,
  );
  assert(facets.Created?.values['2026'] === 5, 'DATETIME Created groups by year');
  assert(facets.Hours?.inputs.filter((t) => t === 'number').length === 2, 'FLOAT Hours with 14 values is a range');
  assert(
    JSON.stringify(Object.keys(facets.Priority?.values ?? {})) === JSON.stringify(['1', '2', '3', '(empty)']),
    'INTEGER Priority with 3 values is a value list, sorted by value',
  );
  assert(facets.Urgent?.values.yes === 3 && facets.Urgent?.values.no === 2, 'BOOLEAN Urgent lists yes/no');
  assert(facets.Blocked?.values.yes === 2 && facets.Blocked?.values.no === 12, 'PROP Blocked lists yes/no');
  assert(!('Secret' in facets), 'PASSWORD Secret is no facet');
  assert(facets.Team?.values['Red team'] === 5, 'OBJECT Team lists its atoms by label');
  await page.click('.facet[data-facet="Team"] .facet__children > button');
  await sleep(300);
  const nested = Object.fromEntries((await readFacets(page)).map((f) => [f.id, f]));
  assert(nested['Team.Lead']?.values.Ann === 9, 'the Team box gives child facets: Team › Lead (Ann: 9)');
  assert(nested['Team.Size'] !== undefined, 'and Team › Size');
  const firstRow = await page.$eval('p-table tbody tr', (e) => e.innerText);
  assert(firstRow.includes('Wrong date format'), 'TABLE annotations reach the table: sorted on Due, ascending');

  console.log('\n▶ Filtering through the panel');
  await clickValue(page, 'Status', 'open');
  assert((await countText(page)) === '7 of 14', `Status=open → 7 of 14 (got: ${await countText(page)})`);
  assert(queryOf(page).getAll('f.Status').join() === 'open', 'the selection is in the URL as f.Status=open');
  let f = Object.fromEntries((await readFacets(page)).map((x) => [x.id, x]));
  assert(f.Tags?.values.bug === 2, `Tags counts follow the Status selection (bug: ${f.Tags?.values.bug})`);
  assert(f.Status?.values.closed === 4, 'Status keeps counting its own alternatives (closed: 4)');
  await clickValue(page, 'Status', 'closed');
  assert((await countText(page)) === '11 of 14', 'two values of one facet: OR → 11 of 14');
  await page.click('.facets__panel-head .facets__link');
  await sleep(400);
  assert((await countText(page)) === '14 of 14', 'Clear all → 14 of 14');

  await page.type('.facets__search input', 'memory');
  await sleep(400);
  assert((await countText(page)) === '1 of 14', 'search "memory" → 1 of 14');
  assert(queryOf(page).get('q') === 'memory', 'the search is in the URL as q=memory');
  await page.click('.facets__panel-head .facets__link');
  await sleep(300);

  await page.type('.facet[data-facet="Hours"] .facet__range input', '5');
  await page.keyboard.press('Tab');
  await sleep(400);
  assert((await countText(page)) === '5 of 14', `Hours ≥ 5 → 5 of 14 (got: ${await countText(page)})`);
  assert(queryOf(page).get('f.Hours') === '5..', 'the range is in the URL as f.Hours=5..');
  await page.click('.facets__panel-head .facets__link');
  await sleep(300);

  await clickValue(page, 'Due', '2026');
  f = Object.fromEntries((await readFacets(page)).map((x) => [x.id, x]));
  assert(f.Due?.values.Sep === 4, 'choosing a year opens its months (Sep: 4)');
  await clickValue(page, 'Due', 'Sep');
  assert((await countText(page)) === '4 of 14', 'Due = Sep 2026 → 4 of 14');
  assert(queryOf(page).getAll('f.Due').join() === '2026-09', 'the month replaces the year: f.Due=2026-09');

  console.log('\n▶ A sort the user chose survives a facet click');
  await open(page, '/tickets');
  const hours = await page.$$('p-table th');
  for (const th of hours) {
    if ((await th.evaluate((e) => e.innerText.trim())) === 'Hours') {
      await th.click();
      break;
    }
  }
  await sleep(400);
  const topRow = () => page.$eval('p-table tbody tr', (e) => e.innerText);
  assert((await topRow()).includes('Broken link'), 'sorted on Hours: Broken link (0.25) first');
  await clickValue(page, 'Status', 'open');
  assert(
    (await topRow()).includes('Add tooltip'),
    `after Status=open still sorted on Hours: Add tooltip (1.0) first (got: ${(await topRow()).split('\n')[0]})`,
  );

  console.log('\n▶ A bookmarked selection');
  await open(page, '/tickets?f.Status=open&f.Team.Lead=Ann&f.Due=2026-09');
  assert((await countText(page)) === '3 of 14', `URL selection → 3 of 14 (got: ${await countText(page)})`);
  const chips = await page.$$eval('.facets__chip', (els) => els.map((e) => e.innerText.trim()));
  assert(chips.length === 3, `three chips (got: ${chips.join(' / ')})`);

  console.log('\n▶ facets and facetOnly');
  await open(page, '/ticketsbyteam');
  const heads = await page.$$eval('p-table th', (els) => els.map((e) => e.innerText.trim()).filter(Boolean));
  assert(!heads.includes('Tags'), `facetOnly Tags is no column (columns: ${heads.join(', ')})`);
  await page.type('.facets__search input', 'no ticket has this text');
  await sleep(400);
  assert(
    (await countText(page)) === '0 of 14' && (await page.$('p-table')) !== null,
    'hideOnNoRecords keeps the table while the box has rows, also when the facets leave none',
  );
  const order = (await readFacets(page)).map((x) => x.title);
  assert(
    JSON.stringify(order) === JSON.stringify(['Status', 'Team › Lead', 'Priority', 'Tags']),
    `facets in the named order (got: ${order.join(', ')})`,
  );

  console.log('\n▶ A FACETS box nested in a FORM');
  await open(page, '/teamdetail/red');
  assert((await countText(page)) === '5 of 5', `Red team has 5 tickets (got: ${await countText(page)})`);
  await clickValue(page, 'Status', 'open');
  assert((await countText(page)) === '3 of 5', 'Status=open → 3 of 5');
  assert(
    queryOf(page).getAll('Tickets.red.f.Status').join() === 'open',
    'a nested box prefixes its parameters with its item name and the enclosing atom',
  );

  console.log('\n▶ facetKind');
  await open(page, '/ticketsreported');
  const chosen = Object.fromEntries((await readFacets(page)).map((f) => [f.id, f]));
  assert(
    chosen.Reported?.values['2026'] === 3,
    `Reported (ALPHANUMERIC) with facetKind=date groups by year (got: ${JSON.stringify(chosen.Reported?.values)})`,
  );
  assert(
    chosen.Reported?.values['(empty)'] === 11,
    `a text without a date counts as (empty) (got: ${chosen.Reported?.values['(empty)']})`,
  );
  assert(chosen.Priority?.inputs.filter((t) => t === 'number').length === 2, 'Priority with facetKind=range is a range');
  await clickValue(page, 'Reported', '2026');
  await clickValue(page, 'Reported', 'Aug');
  assert((await countText(page)) === '2 of 14', `Reported in Aug 2026 → 2 of 14, a text that starts with a date included (got: ${await countText(page)})`);

  // Last, because it adds a row that the counts above do not expect.
  console.log('\n▶ A plain TABLE creates a row once');
  await collectToasts(page);
  await page.goto(`${baseUrl}/newtickets`, { waitUntil: 'networkidle0' });
  await page.waitForSelector('p-table tbody tr');
  const before = await page.$$eval('p-table tbody tr', (r) => r.length);
  await page.click('p-table thead .pi-plus');
  await page.waitForFunction((n) => document.querySelectorAll('p-table tbody tr').length > n, { timeout: 10000 }, before);
  await sleep(1000);
  const after = await page.$$eval('p-table tbody tr', (r) => r.length);
  assert(after === before + 1, `one row more after Create (before: ${before}, after: ${after})`);

  await collectToasts(page);
  const unexpected = [...errors, ...toasts];
  assert(unexpected.length === 0, `no errors (got: ${unexpected.join(' | ') || 'none'})`);
} catch (e) {
  console.error(`  ❌ ${e.message}`);
  failures++;
} finally {
  await browser.close();
}

console.log(failures === 0 ? '\n✅ box-facets: all assertions passed' : `\n❌ box-facets: ${failures} failed`);
process.exit(failures === 0 ? 0 : 1);
