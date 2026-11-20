/**
 * Static check of the Markdown editor (DesignChoices OK-35): where does the code of the editor
 * turn a string into HTML?
 *
 * The editor builds its DOM outside Angular's sanitiser. Its safety rests on ProseMirror creating
 * every element of a document through `document.createElement` from the schema (`toDOM`), never
 * from an HTML string. This script lists every place in the packages that the editor loads where
 * HTML and a string meet (`innerHTML`, `outerHTML`, `insertAdjacentHTML`,
 * `createContextualFragment`, `parseFromString` of the browser's DOMParser, `document.write`),
 * and compares that list with the places reviewed below. A new place, for instance after an
 * upgrade of Milkdown or ProseMirror, fails the check until someone has read it and added it
 * here with the reason it is harmless. ProseMirror's own class DOMParser is no such place: it
 * reads a DOM tree into nodes of the schema and keeps only what a parse rule of the schema knows.
 *
 * Run: node test/projects/markup/e2e/check-editor-html.mjs (after npm install in frontend/).
 * The regression spec of this project runs it too.
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../../../../frontend/node_modules');

/** The packages the editor loads: Milkdown and the ProseMirror packages under it. */
const packages = [
  '@milkdown/core',
  '@milkdown/ctx',
  '@milkdown/prose',
  '@milkdown/preset-commonmark',
  '@milkdown/preset-gfm',
  '@milkdown/plugin-history',
  '@milkdown/transformer',
  '@milkdown/utils',
  '@milkdown/exception',
  'prosemirror-model',
  'prosemirror-view',
  'prosemirror-state',
  'prosemirror-transform',
  'prosemirror-commands',
  'prosemirror-keymap',
  'prosemirror-history',
  'prosemirror-inputrules',
  'prosemirror-schema-list',
  'prosemirror-tables',
  'prosemirror-gapcursor',
  'prosemirror-dropcursor',
  'prosemirror-changeset',
  'prosemirror-safari-ime-span',
];

const pattern = /\b(innerHTML|outerHTML|insertAdjacentHTML|createContextualFragment|parseFromString|document\.write)\b/;

/**
 * The places that were read, as "package/file: the trimmed line", with the reason each is harmless.
 * Filled in from the first run on 20 November 2026 (Milkdown 7.23.0).
 */
export const reviewed = new Map([
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
  const dir = join(root, pkg);
  for (const file of files(dir)) {
    // One build of each package suffices; the CommonJS copies repeat the ES modules.
    if (/[\\/](cjs|umd)[\\/]|\.cjs$/.test(file)) continue;
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
console.log(`\n${found.length} places, ${unreviewed.length} not yet reviewed, ${gone.length} reviewed places no longer present`);
process.exitCode = unreviewed.length === 0 ? 0 : 1;
