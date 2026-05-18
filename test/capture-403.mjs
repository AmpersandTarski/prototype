/**
 * Script om 403-requests te vangen die de "You do not have access to this page" toast veroorzaken.
 */
import puppeteer from 'puppeteer';

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();

const requests403 = [];

// Intercept alle responses
page.on('response', async (resp) => {
  const url = resp.url();
  if (url.includes('api/v1/')) {
    const status = resp.status();
    if (status === 403) {
      let body = '';
      try { body = await resp.text(); } catch {}
      requests403.push({ url, status, snippet: body.substring(0, 300) });
    }
  }
});

// Ga naar de homepage
console.log('Navigating to homepage...');
await page.goto('http://localhost/', { waitUntil: 'networkidle0', timeout: 20000 });
await new Promise(r => setTimeout(r, 3000));

console.log('=== 403 requests na homepage load ===');
console.log(JSON.stringify(requests403, null, 2));

// Reset
requests403.length = 0;

// Ga naar de BoxFilteredDropdownTests pagina
console.log('\nNavigating to BoxFilteredDropdownTests...');
await page.goto('http://localhost/boxfiltereddropdowntests', { waitUntil: 'networkidle0', timeout: 20000 });
await new Promise(r => setTimeout(r, 4000));

console.log('\n=== 403 requests na BoxFilteredDropdownTests load ===');
console.log(JSON.stringify(requests403, null, 2));

await browser.close();
