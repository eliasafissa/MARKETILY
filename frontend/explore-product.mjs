import { chromium } from 'playwright';

const browser = await chromium.connectOverCDP('http://localhost:9222');
const context = browser.contexts()[0] || await browser.newContext();
const page = context.pages()[0] || await context.newPage();

// Go to a category page with products
await page.goto('https://oranosmarket.com/category/67', { waitUntil: 'networkidle', timeout: 60000 });
await page.waitForTimeout(5000);

// Find all links that look like product pages
const links = await page.$$eval('a[href]', as => 
  as.map(a => a.href)
    .filter(h => h.startsWith('https://oranosmarket.com'))
    .filter(h => !h.includes('#'))
    .filter(h => h.includes('/product/') || h.includes('/item/') || h.includes('/p/'))
);

console.log("Product-like links:", links.slice(0, 10));

// Also check for product cards/items on the page
const productElements = await page.$$eval('[class*="product"], [class*="item"], [class*="card"]', els => 
  els.slice(0, 5).map(el => ({
    html: el.outerHTML.slice(0, 500),
    links: Array.from(el.querySelectorAll('a')).map(a => a.href)
  }))
);
console.log("Product elements:", JSON.stringify(productElements, null, 2));

await browser.close();
