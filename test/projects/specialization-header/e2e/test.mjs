/**
 * Regression test for the header of a BOX<FACETS> column that names the specialization all
 * shown rows share (DesignChoices OK-29, Ampersand#1702).
 *
 * Run via `test/run-regression.sh specialization-header` (the runner prepares the backend
 * API; this spec builds the Angular frontend into html/ itself), or against the dev stack:
 * node test/projects/specialization-header/e2e/test.mjs
 *
 * The script:
 * 1. reads the API and asserts `_concepts_`: present, most specific first, on an item named
 *    after a target concept with specializations, in all three storage layouts (one wide
 *    table, MULTITABLE, a union concept), and absent on an item with a label of its own;
 * 2. opens each list, chooses a Kind facet and asserts the header of the identity column,
 *    and that it returns to the label of the modeler when the selection is cleared.
 */
import { assert, baseUrl, buildFrontend, failureCount, loadPuppeteer } from '../../../spec-support/browser-spec.mjs';

const puppeteer = await loadPuppeteer();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function getJson(path) {
  const resp = await fetch(`${baseUrl}${path}`);
  if (!resp.ok) throw new Error(`GET ${path} failed: ${resp.status} ${await resp.text()}`);
  return resp.json();
}

const byId = (rows, item) => Object.fromEntries(rows.map((r) => [r._id_, r[item]]));

console.log('▶ The API lists the specializations of an atom, the most specific first');
const artefacts = byId(await getJson('/api/v1/resource/SESSION/1/Artefacts'), 'Artefact');
assert(JSON.stringify(artefacts.m1?._concepts_) === '["MainClaim","Claim"]', `m1: MainClaim before Claim (got: ${JSON.stringify(artefacts.m1?._concepts_)})`);
assert(JSON.stringify(artefacts.c1?._concepts_) === '["Claim"]', `c1: Claim (got: ${JSON.stringify(artefacts.c1?._concepts_)})`);
assert(JSON.stringify(artefacts.r1?._concepts_) === '["Requirement"]', 'r1: Requirement');
assert(artefacts.a1 && !('_concepts_' in artefacts.a1), 'a1, an Artefact of no specialization, has no _concepts_');
const about = byId(await getJson('/api/v1/resource/SESSION/1/Artefacts'), 'Is_32_about');
const aboutAtoms = Object.values(about).flat().filter(Boolean);
assert(aboutAtoms.length > 0 && aboutAtoms.every((a) => !('_concepts_' in a)), 'a column with a label of its own gets no _concepts_');
const docs = byId(await getJson('/api/v1/resource/SESSION/1/Docs'), 'Doc');
assert(JSON.stringify(docs.rep1?._concepts_) === '["Report"]', `MULTITABLE: rep1 is a Report (got: ${JSON.stringify(docs.rep1?._concepts_)})`);
assert(JSON.stringify(docs.memo1?._concepts_) === '["Memo"]', 'MULTITABLE: memo1 is a Memo');
assert(docs.d1 && !('_concepts_' in docs.d1), 'MULTITABLE: d1, a Doc of no specialization, has no _concepts_');
const items = byId(await getJson('/api/v1/resource/SESSION/1/Items'), 'Item');
assert(JSON.stringify(items.b1?._concepts_) === '["Book"]', `union: b1 is a Book (got: ${JSON.stringify(items.b1?._concepts_)})`);
assert(JSON.stringify(items.f1?._concepts_) === '["Film"]', 'union: f1 is a Film');

buildFrontend('specialization-header');

const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1400, height: 1000 });
const errors = [];
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
page.on('console', (m) => m.type() === 'error' && errors.push(`console: ${m.text()}`));

async function open(path) {
  await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle0', timeout: 30000 });
  await page.waitForSelector('[data-testid=facets-count]', { timeout: 15000 });
  await sleep(500);
}

const headers = () => page.$$eval('p-table thead th', (ths) => ths.map((t) => t.innerText.trim()).filter(Boolean));

async function clickValue(facetId, label) {
  for (const b of await page.$$(`.facet[data-facet="${facetId}"] .facet__value`)) {
    const text = await b.$eval('.facet__label', (e) => e.innerText.trim());
    if (text === label) {
      await (await b.$('input')).click();
      await sleep(400);
      return;
    }
  }
  throw new Error(`no value '${label}' in facet ${facetId}`);
}

async function headerAfter(path, facet, value) {
  await open(path);
  const before = (await headers())[0];
  await clickValue(facet, value);
  const during = (await headers())[0];
  await clickValue(facet, value);
  const after = (await headers())[0];
  return { before, during, after };
}

try {
  console.log('\n▶ One wide table: Artefacts');
  let h = await headerAfter('/artefacts', 'Kind', 'Claim');
  assert(h.before === 'Artefact', `all artefacts: header Artefact (got: ${h.before})`);
  assert(h.during === 'Claim', `Kind=Claim, with a MainClaim among them: header Claim (got: ${h.during})`);
  assert(h.after === 'Artefact', `selection cleared: header Artefact again (got: ${h.after})`);
  h = await headerAfter('/artefacts', 'Kind', 'Requirement');
  assert(h.during === 'Requirement', `Kind=Requirement: header Requirement (got: ${h.during})`);
  h = await headerAfter('/artefacts', 'Kind', 'Other');
  assert(h.during === 'Artefact', `Kind=Other, an atom of no specialization: header stays Artefact (got: ${h.during})`);
  const all = await headers();
  assert(all.includes('Is about'), `a column with a label of its own keeps it (got: ${all.join(', ')})`);

  console.log('\n▶ MULTITABLE: Docs');
  h = await headerAfter('/docs', 'Kind', 'Report');
  assert(h.during === 'Report', `Kind=Report: header Report (got: ${h.during})`);
  assert(h.after === 'Doc', `selection cleared: header Doc (got: ${h.after})`);

  console.log('\n▶ A union concept: Items');
  h = await headerAfter('/items', 'Kind', 'Film');
  assert(h.during === 'Film', `Kind=Film: header Film (got: ${h.during})`);
  assert(h.before === 'Item', `Books and a Film: header Item (got: ${h.before})`);

  assert(errors.length === 0, `no errors (got: ${errors.join(' | ') || 'none'})`);
} catch (e) {
  assert(false, e.message);
} finally {
  await browser.close();
}

const failures = failureCount();
console.log(failures === 0 ? '\n✅ specialization-header: all assertions passed' : `\n❌ specialization-header: ${failures} failed`);
process.exit(failures === 0 ? 0 : 1);
