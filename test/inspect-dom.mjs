import puppeteer from 'puppeteer';

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();

// Navigeer direct naar de interface
await page.goto('http://localhost/boxfiltereddropdowntests', { waitUntil: 'networkidle0', timeout: 15000 });

// Wacht op inhoud
await new Promise(r => setTimeout(r, 2000));

// Zoek de "Default" tab en klik erop als die er is
const tabTexts = await page.$$eval('a.nav-link, [role="tab"]', els => els.map(e => e.textContent.trim()));
console.log('Tabs:', tabTexts);

// Zoek alle secties met hun label en de aanwezige knoppen (trash/delete)
const sections = await page.$$eval('app-atomic-object, [data-testid], .card, .form-group', els =>
  els.slice(0, 30).map(el => ({
    tag: el.tagName,
    classes: el.className,
    text: el.textContent.trim().slice(0, 100),
    html: el.innerHTML.slice(0, 500),
  }))
);
console.log('Sections gevonden:', sections.length);

// Zoek specifiek naar prullebakknop-selectors
const trashButtons = await page.$$eval(
  'button[title*="delete"], button[title*="remove"], button[title*="Delete"], .btn-danger, [class*="trash"], [class*="delete"]',
  els => els.map(e => ({
    tag: e.tagName,
    title: e.getAttribute('title'),
    class: e.className,
    outerHTML: e.outerHTML.slice(0, 200),
    parentText: e.closest('tr, div, td')?.textContent?.trim().slice(0, 80),
  }))
);
console.log('TrashButtons:', JSON.stringify(trashButtons, null, 2));

// Zoek de volledige HTML van alle "3." secties
const section3HTML = await page.evaluate(() => {
  const all = document.querySelectorAll('*');
  const results = [];
  for (const el of all) {
    if (el.textContent.includes('3. Assign an employee') && el.children.length < 5) {
      results.push({ tag: el.tagName, html: el.outerHTML.slice(0, 2000) });
    }
  }
  return results.slice(0, 3);
});
console.log('Section 3 HTML:', JSON.stringify(section3HTML, null, 2));

await browser.close();
