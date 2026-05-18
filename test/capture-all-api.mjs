/**
 * Script om ALLE api/v1 requests te loggen die de browser maakt bij het laden van
 * de BoxFilteredDropdownTests pagina. Zo zien we:
 * - Welke endpoint de interface-data levert
 * - Welke call de 403 veroorzaakt
 */
import puppeteer from 'puppeteer';

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();

const apiCalls = [];

page.on('response', async (resp) => {
  const url = resp.url();
  if (url.includes('api/v1/')) {
    const status = resp.status();
    apiCalls.push({ status, url });
  }
});

console.log('Navigating to BoxFilteredDropdownTests...');
await page.goto('http://localhost/boxfiltereddropdowntests', { waitUntil: 'networkidle0', timeout: 20000 });
await new Promise(r => setTimeout(r, 4000));

console.log('\n=== Alle API calls (status + URL) ===');
for (const c of apiCalls) {
  console.log(`${c.status}  ${c.url}`);
}

await browser.close();
