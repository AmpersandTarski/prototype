/**
 * Diagnose: welke backend requests falen bij "2. setRelation" typen?
 *
 * Doel:
 *  1. Vang ALLE mislukte requests op (500, 403, 4xx)
 *  2. Check of options geladen worden + welke conceptType
 *  3. Check of PATCH wordt verstuurd en of hij slaagt
 */

import puppeteer from 'puppeteer';

const BASE_URL = 'http://localhost';

const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox'],
});

const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 900 });

// Alle requests/responses loggen
const failedRequests = [];
const allRequests = [];    // alle requests (methode + url)
const patchRequests = [];

await page.setRequestInterception(true);
page.on('request', req => {
  allRequests.push({ method: req.method(), url: req.url() });
  req.continue();
});
page.on('response', async resp => {
  const status = resp.status();
  const url = resp.url();
  const method = resp.request().method();
  if (status >= 400) {
    let body = '';
    try { body = (await resp.text()).slice(0, 300); } catch {}
    failedRequests.push({ method, status, url, body });
  }
  if (method === 'PATCH') {
    let body = '';
    try { body = (await resp.text()).slice(0, 500); } catch {}
    patchRequests.push({ status, url, requestBody: resp.request().postData(), responseBody: body });
  }
});

// Verzamel alle console logs
const consoleLogs = [];
page.on('console', msg => consoleLogs.push(`[${msg.type()}] ${msg.text()}`));

// ── Navigeer ──────────────────────────────────────────────────────────────────
await page.goto(`${BASE_URL}/boxfiltereddropdowntests`, { waitUntil: 'networkidle0', timeout: 20000 });
await new Promise(r => setTimeout(r, 1500));

// Tab klikken
await page.evaluate(() => {
  for (const el of document.querySelectorAll('[role="tab"], .p-tabview-nav-link, .nav-link')) {
    if (el.textContent.includes('UNI')) { el.click(); return; }
  }
});

// Wacht op async fetches
console.log('Wacht 5s op options-loading...');
await new Promise(r => setTimeout(r, 5000));

console.log('\n=== ALLE mislukte requests tijdens laden ===');
if (failedRequests.length === 0) {
  console.log('(geen)');
} else {
  failedRequests.forEach(r => {
    console.log(`  HTTP ${r.status} ${r.method} ${r.url}`);
    if (r.body) console.log(`    body: ${r.body}`);
  });
}

// Reset voor de interactie
failedRequests.length = 0;
patchRequests.length = 0;
allRequests.length = 0;

// ── Vind "2. setRelation" input ────────────────────────────────────────────────
const inputHandle = await page.evaluateHandle(() => {
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let node;
  let count = 0;
  while ((node = walker.nextNode())) {
    if (node.textContent.trim() === '2. setRelation') {
      count++;
      if (count === 1) {
        let el = node.parentElement;
        while (el) {
          const inp = el.querySelector('input[type="text"]');
          if (inp) return inp;
          el = el.nextElementSibling || el.parentElement?.nextElementSibling;
          if (!el || el.tagName === 'BODY') break;
        }
      }
    }
  }
  return null;
});

const isEl = await page.evaluate(el => el instanceof HTMLInputElement, inputHandle);
if (!isEl) {
  console.error('❌ Input niet gevonden!');
  await browser.close();
  process.exit(1);
}

const currentVal = await page.evaluate(el => el.value, inputHandle);
const newValue = currentVal === 'm5' ? 'm3' : 'm5';
console.log(`\nHuidige waarde: "${currentVal}" → gaan naar "${newValue}"`);

// ── Interactie: focus + wis + typ + Enter ─────────────────────────────────────
await page.evaluate(el => el.scrollIntoView({ block: 'center' }), inputHandle);
await new Promise(r => setTimeout(r, 300));
await inputHandle.focus();
await new Promise(r => setTimeout(r, 300));
await page.evaluate(el => el.select(), inputHandle);
await page.keyboard.press('Delete');
await new Promise(r => setTimeout(r, 100));
await page.keyboard.type(newValue, { delay: 80 });

const afterTyping = await page.evaluate(el => el.value, inputHandle);
const classAfterTyping = await page.evaluate(el => el.className, inputHandle);
console.log(`Waarde na typen: "${afterTyping}", klasse: ${classAfterTyping}`);

console.log('\nDruk Enter...');
await page.keyboard.press('Enter');
await new Promise(r => setTimeout(r, 3000));

const afterEnter = await page.evaluate(el => el.value, inputHandle);
console.log(`Waarde na Enter: "${afterEnter}"`);

// ── Resultaat ─────────────────────────────────────────────────────────────────
console.log('\n=== Alle requests tijdens interactie ===');
allRequests
  .filter(r => !r.url.includes('.js') && !r.url.includes('.css') && !r.url.includes('.ico'))
  .forEach(r => console.log(`  ${r.method} ${r.url}`));

console.log('\n=== Mislukte requests tijdens interactie ===');
if (failedRequests.length === 0) {
  console.log('(geen)');
} else {
  failedRequests.forEach(r => {
    console.log(`  ❌ HTTP ${r.status} ${r.method} ${r.url}`);
    if (r.body) console.log(`     body: ${r.body}`);
  });
}

console.log('\n=== PATCH requests ===');
if (patchRequests.length === 0) {
  console.log('(geen PATCH verstuurd)');
} else {
  patchRequests.forEach(r => {
    console.log(`  HTTP ${r.status} PATCH ${r.url}`);
    console.log(`  request body: ${r.requestBody}`);
    console.log(`  response: ${r.responseBody}`);
  });
}

console.log('\n════════════════════════════════════════');
console.log('DIAGNOSE:');
if (patchRequests.length === 0 && afterEnter !== afterTyping) {
  console.log(`❌ Geen PATCH + waarde teruggezet: options=[] (fetchDropdownMenuData mislukt)`);
  console.log(`   Kijk naar mislukte requests hierboven voor de failing URL.`);
} else if (patchRequests.length === 0 && afterEnter === afterTyping) {
  console.log(`❌ Geen PATCH + waarde BLIJFT staan: dirty=false of updateValue() werd niet gebeld`);
} else if (patchRequests.some(r => r.status >= 400)) {
  const bad = patchRequests.find(r => r.status >= 400);
  console.log(`❌ PATCH verstuurd maar backend weigerde: HTTP ${bad.status}`);
  console.log(`   URL: ${bad.url}`);
  console.log(`   Request: ${bad.requestBody}`);
  console.log(`   Response: ${bad.responseBody}`);
} else {
  console.log(`✅ PATCH verstuurd en succesvol`);
}

await browser.close();
