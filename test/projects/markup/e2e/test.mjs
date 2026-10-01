/**
 * Regression test for BOX<MARKUP> and app-atomic-markup (DesignChoices OK-24).
 *
 * Run via `test/run-regression.sh markup` (the runner prepares the backend API; this spec
 * builds the Angular frontend into html/ itself), or against the dev stack:
 * node test/projects/markup/e2e/test.mjs
 *
 * The script:
 * 1. copies e2e/templates/Concept-Explanation.html into the template folder, builds the
 *    frontend, and removes the copy again;
 * 2. opens StaticNotes (BOX <MARKUP MARKDOWN>) and asserts the Markdown elements, and that
 *    Markdown without GFM makes no table;
 * 3. opens DynamicNotes (formatFrom on the note) and asserts per row the format, the GFM
 *    table, the sanitised HTML, the escaped plain text, the fallback for RST with its
 *    warning, the default for a note without a format, and that the format item shows
 *    nothing;
 * 4. opens FormatOnText (formatFrom on the text itself);
 * 5. asserts showLabels (LabelledNote), the route per concept (Explanations) and the text
 *    area when the field may be updated (EditExplanation).
 * Every error toast, page error and console error fails the spec.
 */
import { copyFileSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { assert, baseUrl, buildFrontend, failureCount, loadPuppeteer, repoRoot } from '../../../spec-support/browser-spec.mjs';

const puppeteer = await loadPuppeteer();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const conceptTemplate = resolve(repoRoot, 'frontend/src/app/generated/.templates/Concept-Explanation.html');
copyFileSync(resolve(import.meta.dirname, 'templates/Concept-Explanation.html'), conceptTemplate);
try {
  buildFrontend('markup');
} finally {
  rmSync(conceptTemplate, { force: true });
}

const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox'] });
const page = await browser.newPage();
const errors = [];
const warnings = [];
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
page.on('console', (m) => {
  if (m.type() === 'error') errors.push(`console: ${m.text()}`);
  if (m.type() === 'warn' || m.type() === 'warning') warnings.push(m.text());
});

const toasts = [];
async function collectToasts() {
  toasts.push(...(await page.$$eval('.p-toast-message-error', (els) => els.map((e) => e.innerText.replace(/\s+/g, ' ')))));
}

async function open(path, selector) {
  if (page.url().startsWith('http')) await collectToasts();
  await page.goto(`${baseUrl}${path}`, { waitUntil: 'networkidle0', timeout: 30000 });
  await page.waitForSelector(selector, { timeout: 15000 });
  await sleep(300);
}

/** Per table row: the title and every formatted text in it (format, innerHTML, innerText). */
function readRows() {
  return page.$$eval('p-table tbody tr', (rows) =>
    Object.fromEntries(
      // A table inside a formatted text (GFM) has rows of its own; those are no notes.
      rows.filter((tr) => !tr.closest('.markup') && tr.querySelector('td')).map((tr) => [
        tr.querySelector('td')?.innerText.trim(),
        [...tr.querySelectorAll('.markup')].map((m) => ({ format: m.dataset.format, html: m.innerHTML, text: m.innerText })),
      ]),
    ),
  );
}

try {
  console.log('\n▶ StaticNotes: BOX <MARKUP MARKDOWN>');
  await open('/staticnotes', '.markup');
  let rows = await readRows();
  const md = rows.markdown?.[0]?.html ?? '';
  assert(rows.markdown?.[0]?.format === 'MARKDOWN', `the format is MARKDOWN (got: ${rows.markdown?.[0]?.format})`);
  assert(/<h1[^>]*>Heading<\/h1>/.test(md), 'a # line becomes a heading, so \\n in the population is a line break');
  assert(md.includes('<strong>bold</strong>') && md.includes('<code>code</code>'), 'bold and code are formatted');
  assert(/<a href="https:\/\/ampersandtarski\.github\.io"/.test(md), 'a Markdown link becomes a link');
  assert((md.match(/<li>/g) ?? []).length === 2, 'a list gets two items');
  assert(!(rows.gfm?.[0]?.html ?? '').includes('<table'), 'Markdown without GFM makes no table');
  assert(Object.values(rows).every((r) => r.length === 1), 'every row shows one text');

  console.log('\n▶ DynamicNotes: formatFrom on the note');
  warnings.length = 0;
  await open('/dynamicnotes', '.markup');
  rows = await readRows();
  const formats = Object.fromEntries(
    Object.entries(rows)
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([t, r]) => [t, r.map((m) => m.format).join()]),
  );
  assert(
    JSON.stringify(formats) ===
      JSON.stringify({ gfm: 'GFM', html: 'HTML', latex: 'LATEX', markdown: 'MARKDOWN', none: 'MARKDOWN', rst: 'TEXT', text: 'TEXT' }),
    `the format per row, with MARKDOWN as the default and TEXT for RST (got: ${JSON.stringify(formats)})`,
  );
  assert(Object.values(rows).every((r) => r.length === 1), 'the format item shows nothing');
  const gfm = rows.gfm?.[0]?.html ?? '';
  assert(gfm.includes('<table') && gfm.includes('<del>struck</del>'), 'GITHUB-MARKDOWN gives a table and strikethrough');
  const html = rows.html?.[0]?.html ?? '';
  assert(html.includes('<b>html</b>'), 'HTML keeps its markup');
  assert(!html.includes('<script') && !html.includes('onerror') && !/href="javascript:/.test(html) && html.includes('href="unsafe:javascript:'), `HTML is sanitised (got: ${html})`);
  assert((await page.evaluate(() => window.markupPwned)) === undefined, 'no script from the text ran');
  const text = rows.text?.[0] ?? {};
  assert(text.html?.includes('&lt;b&gt;') && !text.html?.includes('<b>'), 'TEXT shows a tag as text');
  assert(text.text?.includes('\n'), 'TEXT keeps its line breaks');
  assert((rows.rst?.[0]?.text ?? '').includes('*emphasis*'), 'RST stays literal');
  assert(
    warnings.some((w) => w.includes("unknown format 'RST'")),
    `an unknown format leaves a warning (got: ${warnings.filter((w) => w.includes('MARKUP')).join(' | ') || 'none'})`,
  );
  assert((rows.none?.[0]?.html ?? '').includes('<strong>the default</strong>'), 'a note without a format gets MARKDOWN');
  // LATEX (DesignChoices OK-25): the mathematics as MathML, the structure formatted, an unknown
  // macro kept readable, and no HTML from the source.
  const latex = rows.latex?.[0]?.html ?? '';
  assert(latex.includes('<math') && latex.includes('<mfrac>'), `LATEX shows a formula as MathML (got: ${latex.slice(0, 200)})`);
  assert(latex.includes('<em>claim</em>') && latex.includes('<blockquote>'), 'LATEX formats emphasis and a quote');
  assert(latex.includes('markup__cmd--lean') && latex.includes('Stack.pile'), 'LATEX keeps the argument of an unknown macro');
  assert(!latex.includes('<script') && latex.includes('&lt;script&gt;'), 'LATEX shows HTML in the source as text');
  assert((await page.evaluate(() => window.markupPwned)) === undefined, 'no script from the LaTeX text ran');

  console.log('\n▶ FormatOnText: formatFrom on the text itself');
  await open('/formatontext', '.markup');
  rows = await readRows();
  assert(rows.markdown?.[0]?.format === 'MARKDOWN' && rows.gfm?.[0]?.format === 'GFM', 'the format of a text follows the text');
  assert(Object.values(rows).every((r) => r.length === 1), 'the format item on the text shows nothing');
  assert(rows.html?.[0]?.format === 'TEXT', `a text without a format and without a default is TEXT (got: ${rows.html?.[0]?.format})`);

  console.log('\n▶ LabelledNote: showLabels');
  await open('/labellednote/n1', '.markup');
  const labels = await page.$$eval('.markup__label', (els) => els.map((e) => e.innerText.trim()));
  assert(JSON.stringify(labels) === '["Body","Explanation"]', `every item has its label (got: ${JSON.stringify(labels)})`);

  console.log('\n▶ Concept-Explanation.html: the route per concept');
  await open('/explanations', '.markup');
  rows = await readRows();
  assert(
    (rows.markdown?.[0]?.html ?? '').includes('<em>explanation</em>'),
    `an Explanation is Markdown without an annotation in the interface (got: ${rows.markdown?.[0]?.html})`,
  );
  await open('/editexplanation/n1', 'textarea');
  assert((await page.$$('app-atomic-markup textarea')).length === 1, 'with update rights the field is the text area');

  await collectToasts();
  const unexpected = [...errors, ...toasts];
  assert(unexpected.length === 0, `no errors (got: ${unexpected.join(' | ') || 'none'})`);
} catch (e) {
  assert(false, e.message);
  console.error(`  at ${page.url()}; errors so far: ${errors.join(' | ') || 'none'}`);
  console.error(`  page text: ${(await page.evaluate(() => document.body.innerText)).slice(0, 600)}`);
} finally {
  await browser.close();
}

const failures = failureCount();
console.log(failures === 0 ? '\n✅ markup: all assertions passed' : `\n❌ markup: ${failures} failed`);
process.exit(failures === 0 ? 0 : 1);
