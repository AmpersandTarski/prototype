/**
 * Regression test for AmpersandTarski/Ampersand#1697: what a browser visit leaves behind, and
 * the link to the privacy statement in the footer.
 *
 * Run via `test/run-regression.sh privacy-and-cookies` (the runner prepares the backend API;
 * this spec builds the Angular frontend into html/ itself). The spec
 * 1. checks the session cookie in the first response of a fresh visitor;
 * 2. builds the frontend and opens it in a headless browser;
 * 3. without frontend.privacyStatementUrl: the footer shows no privacy link;
 * 4. with the setting written to backend/config/project.yaml: the navbar returns it and the
 *    footer links to it in a new tab;
 * 5. checks that every cookie and storage item the visit left is listed in the table
 *    "In the browser" of docs/reference-material/cookies-and-browser-storage.md.
 * project.yaml is restored in the finally below.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import {
  assert,
  baseUrl,
  buildFrontend,
  failureCount,
  loadPuppeteer,
  repoRoot,
} from '../../../spec-support/browser-spec.mjs';
import { documentedStorage } from './helpers/documented-storage.mjs';

const puppeteer = await loadPuppeteer();

const projectYaml = resolve(repoRoot, 'backend/config/project.yaml');
const originalYaml = readFileSync(projectYaml, 'utf8');
const privacyUrl = 'https://example.org/privacy-statement';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// The Set-Cookie headers of the first response to a visitor without a cookie.
async function freshSetCookies(headers = {}) {
  const res = await fetch(`${baseUrl}/api/v1/app/navbar`, { headers });
  return res.headers.getSetCookie();
}

async function navbarPrivacyUrl() {
  const res = await fetch(`${baseUrl}/api/v1/app/navbar`);
  return (await res.json()).privacyStatementUrl ?? null;
}

// The macOS bind mount can serve Apache a stale project.yaml for a short while after the host
// wrote it, so the callers wait until the backend shows the setting instead of trusting the write.
function writeSettings(lines) {
  const settings = lines.map((line) => '  ' + line).join('\n');
  writeFileSync(
    projectYaml,
    lines.length === 0
      ? originalYaml
      : `# TEMPORARY test config, written by test/projects/privacy-and-cookies/e2e/test.mjs\nsettings:\n${settings}\n`,
  );
}

async function waitForPrivacyUrl(expected) {
  const deadline = Date.now() + 20000;
  for (;;) {
    const actual = await navbarPrivacyUrl();
    if (actual === expected) return;
    if (Date.now() > deadline) {
      throw new Error(`privacyStatementUrl did not become ${expected} (last: ${actual})`);
    }
    await sleep(500);
  }
}

async function openHome(page) {
  const client = await page.createCDPSession();
  await client.send('Network.clearBrowserCookies');
  await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle0' });
  await page.waitForSelector('.layout-footer');
  // The footer link follows the navbar response the menu fetches; give it that request.
  await page.waitForFunction(() => document.querySelector('.layout-menu li') !== null, { timeout: 20000 });
}

buildFrontend('privacy-and-cookies');

const browser = await puppeteer.launch({ headless: 'shell', args: ['--no-sandbox'] });
const page = await browser.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));

try {
  console.log('\n▶ The session cookie of a fresh visitor');
  const setCookies = await freshSetCookies();
  assert(setCookies.length === 1, `one Set-Cookie header (got ${setCookies.length}: ${setCookies.join(' | ')})`);
  assert((setCookies[0] ?? '').startsWith('PHPSESSID='), 'it is the session cookie PHPSESSID');
  assert(/;\s*HttpOnly/i.test(setCookies[0] ?? ''), 'it is HttpOnly');

  console.log('\n▶ Without frontend.privacyStatementUrl');
  writeSettings([]);
  await waitForPrivacyUrl(null);
  assert(true, 'the navbar returns privacyStatementUrl null');
  await openHome(page);
  assert((await page.$('a.privacy-statement-link')) === null, 'the footer shows no privacy link');

  console.log('\n▶ With frontend.privacyStatementUrl');
  writeSettings([`frontend.privacyStatementUrl: ${privacyUrl}`]);
  await waitForPrivacyUrl(privacyUrl);
  assert(true, `the navbar returns ${privacyUrl}`);
  await openHome(page);
  const link = await page.$eval('a.privacy-statement-link', (a) => ({
    href: a.getAttribute('href'),
    target: a.getAttribute('target'),
    text: a.textContent.trim(),
  })).catch(() => null);
  assert(link?.href === privacyUrl, `the footer links to the privacy statement (got ${link?.href})`);
  assert(link?.target === '_blank', 'the link opens in a new tab');
  assert(link?.text === 'Privacy and cookies', `the link reads "Privacy and cookies" (got ${link?.text})`);

  console.log('\n▶ What the visit left in the browser is on the reference page');
  const documented = documentedStorage(repoRoot);
  const client = await page.createCDPSession();
  const { cookies } = await client.send('Network.getAllCookies');
  const stored = await page.evaluate(() => ({
    sessionStorage: Object.keys(sessionStorage),
    localStorage: Object.keys(localStorage),
  }));
  const left = [
    ...cookies.map((c) => `cookie:${c.name}`),
    ...stored.sessionStorage.map((k) => `sessionStorage:${k}`),
    ...stored.localStorage.map((k) => `localStorage:${k}`),
  ];
  assert(left.includes('cookie:PHPSESSID'), 'the visit left the session cookie');
  for (const key of left) {
    assert(documented.has(key), `${key} is documented`);
  }
  assert(errors.length === 0, 'no page errors' + (errors.length ? ': ' + errors.join('; ') : ''));
} catch (e) {
  assert(false, e.message);
} finally {
  writeFileSync(projectYaml, originalYaml);
  await browser.close();
}

if (failureCount() > 0) {
  console.error(`\n${failureCount()} failure(s)`);
  process.exit(1);
}
console.log('\nAll checks passed.');
