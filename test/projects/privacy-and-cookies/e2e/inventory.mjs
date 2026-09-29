/**
 * Static inventory of what the framework stores in the browser (AmpersandTarski/Ampersand#1697).
 *
 * Scans the framework sources for every call that writes a cookie or browser storage and
 * compares the keys with the table "In the browser" of
 * docs/reference-material/cookies-and-browser-storage.md, in both directions:
 *   - a key in the code that the page does not list fails ("add a row to the page");
 *   - a row on the page that no code writes fails ("the row is stale").
 * A write whose key is not a string literal fails too, because its key cannot be checked.
 *
 * Needs no running stack: node test/projects/privacy-and-cookies/e2e/inventory.mjs
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { documentedStorage } from './helpers/documented-storage.mjs';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../../..');

let failures = 0;
function assert(cond, msg) {
  if (cond) {
    console.log(`  ✅ ${msg}`);
  } else {
    console.error(`  ❌ ${msg}`);
    failures++;
  }
}

function* sourceFiles(dir, extension) {
  for (const entry of readdirSync(dir)) {
    if (entry === 'node_modules' || entry === 'dist') continue;
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) {
      yield* sourceFiles(path, extension);
    } else if (path.endsWith(extension) && !/\.(spec|stories)\.ts$/.test(path)) {
      yield path;
    }
  }
}

const found = new Map(); // "kind:name" -> first place where the code writes it
const unchecked = []; // writes whose key is not a literal, or storage the page cannot list
function record(key, where) {
  if (!found.has(key)) found.set(key, where);
}

// Frontend: framework code, including the templates the compiler copies into generated code.
for (const file of sourceFiles(resolve(repoRoot, 'frontend/src'), '.ts')) {
  const lines = readFileSync(file, 'utf8').split('\n');
  lines.forEach((line, i) => {
    const where = `${relative(repoRoot, file)}:${i + 1}`;
    for (const [, kind, name] of line.matchAll(/(sessionStorage|localStorage)\.setItem\(\s*['"]([^'"]+)['"]/g)) {
      record(`${kind}:${name}`, where);
    }
    for (const [, name] of line.matchAll(/setSessionStorageItem\(\s*['"]([^'"]+)['"]/g)) {
      record(`sessionStorage:${name}`, where);
    }
    // The key of a call spread over several lines sits on the next line.
    if (/setSessionStorageItem\(\s*$/.test(line)) {
      const next = lines[i + 1]?.match(/^\s*['"]([^'"]+)['"]/);
      if (next) record(`sessionStorage:${next[1]}`, where);
      else unchecked.push(`${where}: setSessionStorageItem with a key that is not a literal`);
    }
    // The only write with a variable key allowed is the body of the setSessionStorageItem
    // wrappers, whose callers are checked above.
    if (/(sessionStorage|localStorage)\.setItem\(\s*[^'"\s]/.test(line) && !/sessionStorage\.setItem\(name, data\)/.test(line)) {
      unchecked.push(`${where}: storage write with a key that is not a literal`);
    }
    if (/document\.cookie\s*=|indexedDB|caches\.open|ngx-cookie|CookieService/.test(line)) {
      unchecked.push(`${where}: cookie or storage API the page does not cover`);
    }
  });
}

// Backend: the session cookie comes from session_start(); any other cookie is set explicitly.
let sessionStarts = 0;
for (const dir of ['backend/src', 'backend/bootstrap', 'backend/public', 'backend/config']) {
  for (const file of sourceFiles(resolve(repoRoot, dir), '.php')) {
    readFileSync(file, 'utf8').split('\n').forEach((line, i) => {
      const where = `${relative(repoRoot, file)}:${i + 1}`;
      if (/^\s*(\/\/|\*|#)/.test(line)) return;
      if (/\bsession_start\(/.test(line)) {
        sessionStarts++;
        record('cookie:PHPSESSID', where);
      }
      if (/\b(setcookie|setrawcookie|session_name)\(|Set-Cookie/i.test(line)) {
        unchecked.push(`${where}: sets or renames a cookie`);
      }
    });
  }
}

const documented = documentedStorage(repoRoot);

console.log('▶ Every cookie and storage key the code writes is on the reference page');
for (const [key, where] of found) {
  assert(documented.has(key), `${key} (${where}) is documented`);
}
console.log('\n▶ Every row on the reference page is still written by the code');
for (const key of documented) {
  assert(found.has(key), `${key} is written somewhere in the code`);
}
console.log('\n▶ Every write can be checked');
assert(sessionStarts === 1, `the session starts in one place (found ${sessionStarts})`);
for (const u of unchecked) assert(false, u);
if (unchecked.length === 0) assert(true, 'no write with an unchecked key or API');

if (failures > 0) {
  console.error(
    `\n${failures} failure(s). Update docs/reference-material/cookies-and-browser-storage.md ` +
      'together with the code that stores something in the browser.',
  );
  process.exit(1);
}
console.log('\nInventory matches the reference page.');
