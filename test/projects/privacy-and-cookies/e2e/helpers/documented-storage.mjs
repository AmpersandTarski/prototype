/**
 * Reads the table "In the browser" of docs/reference-material/cookies-and-browser-storage.md.
 * That page is what a privacy officer copies into a privacy statement (AmpersandTarski/Ampersand#1697),
 * so the specs of this project check the code and a browser visit against it.
 *
 * Returns a Set of "kind:name" strings, e.g. "cookie:PHPSESSID" and "sessionStorage:menuItems".
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

export function documentedStorage(repoRoot) {
  const page = readFileSync(
    resolve(repoRoot, 'docs/reference-material/cookies-and-browser-storage.md'),
    'utf8',
  );
  const section = page.split(/^## /m).find((s) => s.startsWith('In the browser'));
  if (!section) {
    throw new Error('The reference page has no section "In the browser"');
  }
  const rows = [...section.matchAll(/^\| `([^`]+)` \| (cookie|sessionStorage|localStorage) \|/gm)];
  return new Set(rows.map(([, name, kind]) => `${kind}:${name}`));
}
