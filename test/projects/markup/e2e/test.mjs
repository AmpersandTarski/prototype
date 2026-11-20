/**
 * Regression test for BOX<MARKUP> and app-atomic-markup (DesignChoices OK-24).
 *
 * Run via `test/run-regression.sh markup` (the runner prepares the backend API; this spec
 * builds the Angular frontend into html/ itself), or against the dev stack:
 * node test/projects/markup/e2e/test.mjs
 *
 * The script:
 * 1. copies the templates of e2e/templates/ into the template folder, builds the frontend, and
 *    removes the copies again;
 * 2. opens StaticNotes (BOX <MARKUP MARKDOWN>) and asserts the Markdown elements, and that
 *    Markdown without GFM makes no table;
 * 3. opens DynamicNotes (formatFrom on the note) and asserts per row the format, the GFM
 *    table, the sanitised HTML, the escaped plain text, the fallback for RST with its
 *    warning, the default for a note without a format, and that the format item shows
 *    nothing;
 * 4. opens FormatOnText (formatFrom on the text itself);
 * 5. asserts showLabels (LabelledNote), the route per concept (Explanations) and the Markdown
 *    editor when the field may be updated (EditExplanation);
 * 6. for the Markdown editor (DesignChoices OK-35; the static check is check-editor-html.mjs):
 *    opens the attack texts of Sample in the reading mode (ReadSamples) and in the editing mode
 *    (EditSamples) and asserts that none ran and that no dangerous element or attribute is on the
 *    page, enters and leaves every editor without typing and asserts that no PATCH went out and
 *    every stored text is unchanged, types in two texts (EditSample) and asserts what is stored,
 *    and asserts the text area for LATEX and for a field with more texts.
 * Every error toast, page error and console error fails the spec.
 */
import { copyFileSync, readdirSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { assert, baseUrl, buildFrontend, failureCount, loadPuppeteer, repoRoot } from '../../../spec-support/browser-spec.mjs';

const puppeteer = await loadPuppeteer();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const templateDir = resolve(repoRoot, 'frontend/src/app/generated/.templates');
const templates = readdirSync(resolve(import.meta.dirname, 'templates'));
for (const t of templates) copyFileSync(resolve(import.meta.dirname, 'templates', t), resolve(templateDir, t));
try {
  buildFrontend('markup');
} finally {
  for (const t of templates) rmSync(resolve(templateDir, t), { force: true });
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

/**
 * Every dangerous element or attribute inside the elements that `selector` names: an element
 * outside the tags that Markdown, the sanitiser and the editor produce, an event handler, or a
 * URL with a scheme that runs or embeds code.
 */
function dangers(selector) {
  return page.$$eval(selector, (roots) => {
    const tags = new Set(
      'div span p a img strong em b i code pre ul ol li blockquote h1 h2 h3 h4 h5 h6 hr br table thead tbody tr th td del s details summary'.split(' '),
    );
    const urlAttrs = new Set(['href', 'src', 'xlink:href', 'action', 'formaction', 'data', 'srcdoc', 'poster', 'background']);
    const found = [];
    for (const root of roots) {
      for (const el of root.querySelectorAll('*')) {
        const tag = el.tagName.toLowerCase();
        if (!tags.has(tag)) found.push(`<${tag}>`);
        for (const a of el.attributes) {
          if (/^on/i.test(a.name)) found.push(`${tag}[${a.name}]`);
          // A URL with another scheme than the four that safeUrl allows, or into the administration
          // routes. `unsafe:` is the prefix with which Angular's sanitiser makes raw HTML's URL inert.
          const scheme = /^\s*([a-z][a-z0-9+.-]*):/i.exec(a.value)?.[1]?.toLowerCase();
          const unsafe = (scheme && !['http', 'https', 'mailto', 'tel', 'unsafe'].includes(scheme)) || /api\/v1\/(%61|a)dmin/i.test(a.value);
          if (urlAttrs.has(a.name.toLowerCase()) && unsafe) found.push(`${tag}[${a.name}=${a.value.slice(0, 40)}]`);
        }
      }
    }
    return found;
  });
}

/** Moves the mouse over and focuses every element inside `selector`, so that a handler that waits for it would run. */
function provoke(selector) {
  return page.$$eval(selector, (roots) => {
    for (const root of roots)
      for (const el of root.querySelectorAll('*'))
        for (const type of ['mouseover', 'mouseenter', 'focus', 'toggle', 'start']) el.dispatchEvent(new Event(type, { bubbles: true }));
  });
}

/** The data of an interface, read through the API in the session of the page. */
function api(path) {
  return page.evaluate((p) => fetch(`/api/v1/resource/${p}`, { headers: { Accept: 'application/json' } }).then((r) => r.json()), path);
}

/** The stored texts of every sample, by sample and field. */
async function storedSamples() {
  const rows = await api('SESSION/1/EditSamples');
  return Object.fromEntries(rows.map((r) => [r._id_, { text: r.text ?? null, readme: r.readme ?? null }]));
}

const editorSelector = 'app-markdown-editor .ProseMirror';

async function waitForEditors(count) {
  await page.waitForFunction((sel, n) => document.querySelectorAll(sel).length >= n, { timeout: 30000 }, editorSelector, count);
  await sleep(300);
}

const patches = [];
// Requests to the administration routes that the page itself makes (issue #548); the spec's own
// installer call goes through the runner, outside the browser.
const adminRequests = [];
page.on('request', (r) => {
  if (r.method() === 'PATCH') patches.push(r.url());
  if (/\/api\/v1\/admin\b/i.test(new URL(r.url()).pathname)) adminRequests.push(r.url());
});

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
  await open('/editexplanation/n1', editorSelector);
  assert(
    (await page.$$(editorSelector)).length === 1 && (await page.$$('app-atomic-markup textarea')).length === 0,
    'with update rights a Markdown field is the editor, not the text area',
  );

  // The Markdown editor (DesignChoices OK-35).
  console.log('\n▶ ReadSamples: the attack texts in the reading mode');
  const before = await storedSamples();
  const attacks = Object.keys(before).filter((id) => id.startsWith('x'));
  assert(attacks.length === 34, `the model has 34 attack texts (got: ${attacks.length})`);
  await open('/readsamples', '.markup');
  await provoke('app-atomic-markup');
  await sleep(500);
  let found = await dangers('app-atomic-markup');
  assert(found.length === 0, `the reading mode shows no dangerous element or attribute (got: ${found.join(' ') || 'none'})`);
  assert((await page.evaluate(() => window.__xss)) === undefined, `no attack text ran in the reading mode (got: ${await page.evaluate(() => window.__xss)})`);

  console.log('\n▶ EditSamples: the attack texts in the editing mode');
  // Two fields per sample, text and readme; an empty field is an editor too.
  const fields = Object.keys(before).length * 2;
  await open('/editsamples', 'app-markdown-editor');
  await waitForEditors(fields);
  assert((await page.$$(editorSelector)).length === fields, `every text field of a sample is an editor (${fields})`);
  await provoke('app-markdown-editor');
  await sleep(500);
  found = await dangers('app-markdown-editor');
  assert(found.length === 0, `the editing mode shows no dangerous element or attribute (got: ${found.join(' ') || 'none'})`);
  assert((await page.evaluate(() => window.__xss)) === undefined, `no attack text ran in the editing mode (got: ${await page.evaluate(() => window.__xss)})`);
  assert(adminRequests.length === 0, `no text made the page call an administration route (got: ${adminRequests.join(' ') || 'none'})`);
  const rawHtml = await page.$$eval('app-markdown-editor [data-type="html"]', (els) => els.length);
  assert(rawHtml > 0, `raw HTML in a text is a node that shows its source (${rawHtml})`);
  const images = await page.$$eval('app-markdown-editor img:not(.ProseMirror-separator)', (els) => els.map((e) => e.getAttribute('src')));
  assert(images.length >= 2 && images.every((s) => !/^\s*(javascript|data):/i.test(s)), `an image with a javascript: or data: URL gets an empty src (got: ${JSON.stringify(images)})`);

  console.log('\n▶ EditSamples: enter and leave every editor without typing');
  patches.length = 0;
  const editors = await page.$$(editorSelector);
  for (const editor of editors) {
    await editor.click();
    await sleep(50);
  }
  await page.mouse.click(2, 2);
  await sleep(1500);
  assert(patches.length === 0, `leaving an editor without typing sends no PATCH (got: ${patches.length})`);
  const after = await storedSamples();
  const changed = Object.keys(before).filter((id) => JSON.stringify(before[id]) !== JSON.stringify(after[id]));
  assert(changed.length === 0, `every stored text is unchanged, letter for letter (changed: ${changed.join(', ') || 'none'})`);

  console.log('\n▶ EditSample: type in a text, and the rest stays as stored');
  await open('/editsample/e1', editorSelector);
  await waitForEditors(1);
  assert((await page.$$('app-atomic-markup textarea')).length === 2, 'LATEX and a field with more texts keep the text area');
  patches.length = 0;
  await page.click(`${editorSelector} p:last-of-type`);
  await page.keyboard.press('End');
  await page.keyboard.type(' Edited.');
  let saved = page.waitForResponse((r) => r.request().method() === 'PATCH', { timeout: 15000 });
  await page.mouse.click(2, 2);
  await saved;
  await sleep(500);
  let stored = (await api('Sample/e1/EditSample')).text;
  assert(
    stored === 'First paragraph with -toets\\.(py|sh)$ in it.\n\nSecond paragraph. Edited.',
    `the edit is stored, and the untouched paragraph keeps its backslash (got: ${JSON.stringify(stored)})`,
  );

  console.log('\n▶ EditSample: type bold and a list in the formatted text');
  await open('/editsample/e2', editorSelector);
  await waitForEditors(1);
  await page.click(`${editorSelector} p`);
  await page.keyboard.press('End');
  await page.keyboard.press('Enter');
  await page.keyboard.type('Some **bold** text.');
  await page.keyboard.press('Enter');
  await page.keyboard.type('- an item');
  const strong = await page.$$eval(`${editorSelector} strong`, (els) => els.map((e) => e.innerText));
  const items = await page.$$eval(`${editorSelector} li`, (els) => els.map((e) => e.innerText.trim()));
  assert(strong.includes('bold') && items.includes('an item'), `the editor formats bold and a list while typing (got: ${JSON.stringify({ strong, items })})`);
  saved = page.waitForResponse((r) => r.request().method() === 'PATCH', { timeout: 15000 });
  await page.mouse.click(2, 2);
  await saved;
  await sleep(500);
  stored = (await api('Sample/e2/EditSample')).text ?? '';
  assert(
    stored.startsWith('Start.\n\nSome **bold** text.') && /^[-*] an item$/m.test(stored),
    `the stored Markdown holds the bold text and the list (got: ${JSON.stringify(stored)})`,
  );

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
