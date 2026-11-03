/**
 * Regression test for rights that differ per atom: a box whose term selects the atoms it may change.
 *
 * Run: test/run-regression.sh box-guarded-edit
 *
 * An atom that the term selects shows the box, can be updated through it and can be deleted through it.
 * For any other atom the box is empty, and an update through it is refused.
 */
import { assert, baseUrl, buildFrontend, failureCount, loadPuppeteer } from '../../../spec-support/browser-spec.mjs';

async function call(method, path, body) {
  const resp = await fetch(`${baseUrl}/api/v1/resource/${path}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const text = await resp.text();
  let json = null;
  try {
    json = JSON.parse(text);
  } catch {
    json = null;
  }
  return { status: resp.status, json, text };
}

// A box whose term is not I has the atom in its path, as its _path_ shows.
const edit = (id) => [{ op: 'replace', path: `/Edit/${id}/Text`, value: `new ${id}` }];

// A response goes to the log on one line, so that its text cannot pose as a line of the test.
const oneLine = (s) => String(s).replace(/[\r\n]+/g, ' ');

const here = await call('GET', 'Choice/here/Choice');
console.log('GET here:', oneLine(JSON.stringify(here.json)));
assert(here.status === 200, 'an atom whose home is here can be read');
assert(here.json?.Edit?.Text === 'old here', 'it shows the box with its text');

const elsewhere = await call('GET', 'Choice/elsewhere/Choice');
console.log('GET elsewhere:', oneLine(JSON.stringify(elsewhere.json)));
assert(elsewhere.status === 200, 'an atom that lives elsewhere can be read');
assert(!elsewhere.json?.Edit, 'its box is empty');
assert(elsewhere.json?.Contents?.Text === 'old elsewhere', 'its contents are shown read-only');

console.log('\n▶ The screens, in a built frontend');
const puppeteer = await loadPuppeteer();
buildFrontend('box-guarded-edit');
// 'shell' renders without a display; see box-facets for why.
const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1200, height: 800 });
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
async function show(id) {
  await page.goto(`${baseUrl}/#/Choice/${id}`, { waitUntil: 'networkidle0' }).catch(() => {});
  await page.goto(`${baseUrl}/choice/${id}`, { waitUntil: 'networkidle0' });
  await page.waitForFunction(() => document.body.innerText.includes('Contents'), { timeout: 30000 });
  return page.evaluate(() => ({
    text: document.body.innerText,
    // The menu bar has an input as well; only those in a box count here.
    inputs: [...document.querySelectorAll('.box-form-value input, .box-form-value textarea')].map((e) => e.value),
    trash: document.querySelectorAll('.pi-trash').length,
  }));
}
const screenHere = await show('here');
console.log('screen here:', JSON.stringify(screenHere));
// The stylesheet writes a label in capitals.
assert(screenHere.text.includes('EDIT'), 'the screen of an atom whose home is here shows the box');
assert(screenHere.inputs.includes('old here'), 'with a field in which its text can be edited');
assert(screenHere.trash === 1, 'and one button to delete it');
const screenElsewhere = await show('elsewhere');
console.log('screen elsewhere:', JSON.stringify(screenElsewhere));
assert(!screenElsewhere.text.includes('EDIT'), 'the screen of an atom that lives elsewhere leaves the box out');
assert(screenElsewhere.inputs.length === 0, 'and has no field to edit');
assert(screenElsewhere.trash === 0, 'and no button to delete it');
assert(screenElsewhere.text.includes('old elsewhere'), 'and shows its contents');
assert(errors.length === 0, `no errors on the page (got: ${errors.join('; ')})`);
await browser.close();

console.log('\n▶ Writing, over the API');
const patchHere = await call('PATCH', 'Choice/here/Choice', edit('here'));
console.log('PATCH here:', patchHere.status, oneLine(patchHere.text.slice(0, 300)));
assert(patchHere.status === 200 && patchHere.json?.isCommitted === true, 'an update through the box is committed');
const again = await call('GET', 'Choice/here/Choice');
assert(again.json?.Contents?.Text === 'new here', 'the read-only block shows the new text');

const patchElsewhere = await call('PATCH', 'Choice/elsewhere/Choice', edit('elsewhere'));
console.log('PATCH elsewhere:', patchElsewhere.status, oneLine(patchElsewhere.text.slice(0, 300)));
assert(patchElsewhere.json?.isCommitted !== true, 'an update of an atom that lives elsewhere is not committed');
const stillOld = await call('GET', 'Choice/elsewhere/Choice');
assert(stillOld.json?.Contents?.Text === 'old elsewhere', 'its text stays what it was');

const removeElsewhere = await call('DELETE', 'Choice/elsewhere/Choice/Edit/elsewhere');
console.log('DELETE elsewhere:', removeElsewhere.status, oneLine(removeElsewhere.text.slice(0, 300)));
const stillThere = await call('GET', 'Choice/elsewhere/Choice');
assert(stillThere.json?.Contents?.Text === 'old elsewhere', 'an atom that lives elsewhere cannot be deleted through the box');

const removeHere = await call('DELETE', 'Choice/here/Choice/Edit/here');
console.log('DELETE here:', removeHere.status, oneLine(removeHere.text.slice(0, 300)));
assert(removeHere.status === 200, 'an atom whose home is here can be deleted through the box');
const gone = await call('GET', 'Choice/here/Choice');
console.log('GET here after delete:', gone.status);
assert(gone.status === 404 || gone.json?.Contents === null, 'and then it is gone');

process.exit(failureCount() ? 1 : 0);
