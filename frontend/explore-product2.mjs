import { chromium } from 'playwright';

const browser = await chromium.connectOverCDP('http://localhost:9222');
const context = browser.contexts()[0] || await browser.newContext();
const page = context.pages()[0] || await context.newPage();

// Intercept network requests to see API calls
page.on('response', async (response) => {
  const url = response.url();
  if (url.includes('/api/') && (url.includes('product') || url.includes('item'))) {
    try {
      const body = await response.json();
      console.log("API Response:", url, JSON.stringify(body).slice(0, 500));
    } catch (e) {}
  }
});

// Go to a category page with products
await page.goto('https://oranosmarket.com/category/67', { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(8000);

// Get all links
const allLinks = await page.$$eval('a[href]', as => 
  as.map(a => a.href)
    .filter(h => h.startsWith('https://oranosmarket.com'))
    .filter(h => !h.includes('#'))
);
console.log("All links:", allLinks.slice(0, 30));

await browser.close();
