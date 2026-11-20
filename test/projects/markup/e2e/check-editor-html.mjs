/**
 * Static check of the Markdown editor (DesignChoices OK-35): where does the code of the editor
 * turn a string into HTML?
 *
 * The editor builds its DOM outside Angular's sanitiser. Its safety rests on ProseMirror creating
 * every element of a document through `document.createElement` from the schema (`toDOM`), never
 * from an HTML string. This script lists every place where HTML and a string meet in the
 * packages that the editor imports and in every package under them, as package-lock.json names
 * them (`innerHTML`, `outerHTML`, `insertAdjacentHTML`,
 * `createContextualFragment`, `parseFromString` of the browser's DOMParser, `document.write`),
 * and compares that list with the places reviewed below. A new place, for instance after an
 * upgrade of Milkdown or ProseMirror, fails the check until someone has read it and added it
 * here with the reason it is harmless. ProseMirror's own class DOMParser is no such place: it
 * reads a DOM tree into nodes of the schema and keeps only what a parse rule of the schema knows.
 *
 * Run: node test/projects/markup/e2e/check-editor-html.mjs (after npm install in frontend/).
 * test/run-regression.sh markup runs it, as it runs every script in e2e/.
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const frontend = resolve(import.meta.dirname, '../../../../frontend');
const root = join(frontend, 'node_modules');

/** The packages the editor imports (markdown-editor.component.ts, markdown-merge.ts). */
const imported = [
  '@milkdown/core',
  '@milkdown/preset-commonmark',
  '@milkdown/preset-gfm',
  '@milkdown/plugin-history',
  '@milkdown/utils',
  'node-diff3',
];

/**
 * Those packages and every package under them (remark, micromark, ProseMirror, ...), from the
 * lock file. Type packages (@types/*) hold no code that runs.
 */
function closure() {
  const lock = JSON.parse(readFileSync(join(frontend, 'package-lock.json'), 'utf8')).packages;
  // A dependency of the package at `dir` resolves to dir/node_modules/<name> when that copy
  // exists (a nested copy of another version), and otherwise one level up, to the top.
  const resolveIn = (dir, name) => {
    for (let d = dir; d !== ''; d = d.includes('/node_modules/') ? d.slice(0, d.lastIndexOf('/node_modules/')) : '') {
      if (lock[`${d}/node_modules/${name}`]) return `${d}/node_modules/${name}`;
    }
    if (lock[`node_modules/${name}`]) return `node_modules/${name}`;
    throw new Error(`${name} (a dependency of ${dir || 'the editor'}) is not in package-lock.json`);
  };
  const seen = new Set();
  const todo = imported.map((name) => resolveIn('', name));
  while (todo.length > 0) {
    const dir = todo.pop();
    if (seen.has(dir)) continue;
    seen.add(dir);
    const entry = lock[dir];
    for (const name of [...Object.keys(entry.dependencies ?? {}), ...Object.keys(entry.peerDependencies ?? {})]) {
      if (!name.startsWith('@types/')) todo.push(resolveIn(dir, name));
    }
  }
  return [...seen].sort((a, b) => a.localeCompare(b));
}

/** The folders of those packages, relative to frontend/, such as node_modules/micromark. */
const packages = closure();

/**
 * Where a string becomes HTML or code: the HTML sinks of the DOM, and eval, new Function, srcdoc
 * and an event handler set as an attribute.
 */
const pattern =
  /\b(innerHTML|outerHTML|insertAdjacentHTML|createContextualFragment|parseFromString|document\.write|srcdoc)\b|\beval\s*\(|\bnew\s+Function\b|setAttribute\(\s*["'`]on/;

/**
 * The places that were read, as "package/file: the trimmed line", with the reason each is harmless.
 * Filled in from the runs on 20 November 2026 (Milkdown 7.23.0, node-diff3 3.2.1).
 */
export const reviewed = new Map([
  [
    'decode-named-character-reference/index.dom.js: element.innerHTML = characterReference',
    'decodes a named character reference such as &amp; by letting the browser parse "&name;" in an <i> of the page that is never inserted into it. Such an element would still run the onerror of an <img> parsed into it, so the safety rests on the input: micromark passes only a name of at most 31 ASCII letters and digits (micromark-core-commonmark/lib/character-reference.js, and micromark-util-decode-string with a name of 1 to 31 letters and digits), so no tag can form.',
  ],
  [
    '@milkdown/utils/lib/index.js: return div.innerHTML;',
    'getHTML() reads the HTML of a document as a string, for output; the editor does not call it, and reading innerHTML creates no element.',
  ],
  [
    'prosemirror-view/dist/index.js: // elements for innerHTML to work. I.e. if you do `div.innerHTML =',
    'a comment',
  ],
  [
    'prosemirror-view/dist/index.js: // innerHTML, even on a detached document. This wraps the string in',
    'a comment',
  ],
  [
    'prosemirror-view/dist/index.js: elt.innerHTML = maybeWrapTrusted(html);',
    'readHTML() for pasted HTML: the string is parsed into the body of a detached document (document.implementation.createHTMLDocument), where no script runs, no event handler fires and no image loads; the schema parser then keeps only the nodes of the schema.',
  ],
  [
    'prosemirror-view/dist/index.js: data.setData("text/html", dom.innerHTML);',
    'copy: reads the HTML of the selection into the clipboard; reading innerHTML creates no element.',
  ],
  [
    'prosemirror-view/dist/index.js: doPaste(view, target.textContent, target.innerHTML, plain, event);',
    'capturePaste(), only for browsers without a usable clipboard API (Internet Explorer, iOS before WebKit 604): reads what the person pasted from their own clipboard, and passes it to readHTML() above.',
  ],
  [
    'prosemirror-view/dist/index.js: event.dataTransfer.setData(brokenClipboardAPI ? "Text" : "text/html", dom.innerHTML);',
    'drag: reads the HTML of the dragged selection into the drag data; reading innerHTML creates no element.',
  ],
]);

function* files(dir) {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) {
      if (name !== 'node_modules') yield* files(path);
    } else if (/\.(m?js|cjs)$/.test(name) && !/\.(min|map)\./.test(name)) yield path;
  }
}

const found = [];
for (const pkg of packages) {
  // A nested copy has a folder of its own in the list, so files() skips node_modules.
  for (const file of files(join(frontend, pkg))) {
    // One build of each package suffices; the CommonJS copies repeat the ES modules.
    if (/(?:[\\/](?:cjs|umd)[\\/])|(?:\.cjs$)/.test(file)) continue;
    readFileSync(file, 'utf8')
      .split('\n')
      .forEach((line, i) => {
        if (pattern.test(line)) found.push({ key: `${relative(root, file)}: ${line.trim().slice(0, 160)}`, at: `${relative(root, file)}:${i + 1}` });
      });
  }
}

const unreviewed = found.filter((f) => !reviewed.has(f.key));
const gone = [...reviewed.keys()].filter((k) => !found.some((f) => f.key === k));
for (const f of found) console.log(`${reviewed.has(f.key) ? '  read ' : '  NEW  '} ${f.at}\n        ${f.key.split(': ').slice(1).join(': ')}`);
for (const k of gone) console.log(`  GONE  ${k}`);
console.log(`\n${packages.length} packages (${packages.filter((p) => p.lastIndexOf('node_modules/') > 0).length} nested), ${found.length} places, ${unreviewed.length} not yet reviewed, ${gone.length} reviewed places no longer present`);
process.exitCode = unreviewed.length === 0 ? 0 : 1;
