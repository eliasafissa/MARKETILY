import { chromium } from 'playwright';
import { writeFileSync, mkdirSync, existsSync } from 'fs';

const images = new Map();
const seenPages = new Set();
const queuedPages = ['https://oranosmarket.com/'];

const storageDir = 'storage/app';
if (!existsSync(storageDir)) {
    mkdirSync(storageDir, { recursive: true });
}

const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage']
});

const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
});

while (queuedPages.length > 0 && seenPages.size < 30) {
    const url = queuedPages.shift();
    if (seenPages.has(url)) continue;
    seenPages.add(url);

    const page = await context.newPage();

    page.on('response', (response) => {
        const u = response.url();
        if (u.includes('/images/') && /\.(webp|jpg|jpeg|png|gif)/i.test(u)) {
            images.set(u, { url: u, alt: '', page: url });
        }
    });

    try {
        console.log('Crawling: ' + url);
        await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });

        for (let i = 0; i < 4; i++) {
            await page.evaluate(() => window.scrollBy(0, 1200));
            await page.waitForTimeout(600);
        }
        await page.waitForTimeout(2000);

        const domImgs = await page.$$eval('img', (imgs) =>
            imgs.map(img => ({
                src: img.src,
                alt: img.alt || '',
                nearText: (img.closest('a,li,article,div')?.innerText || '').trim().slice(0, 120)
            })).filter(i => i.src && i.src.includes('/images/'))
        );

        for (const img of domImgs) {
            images.set(img.src, {
                url: img.src,
                alt: img.alt,
                nearText: img.nearText,
                page: url,
            });
        }

        const links = await page.$$eval('a[href]', (as) =>
            as.map(a => a.href)
                .filter(h => h.startsWith('https://oranosmarket.com'))
                .filter(h => !h.includes('#'))
        );
        for (const link of links) {
            if (!seenPages.has(link) && queuedPages.length < 100) {
                queuedPages.push(link);
            }
        }
    } catch (e) {
        console.log('Error on ' + url + ': ' + e.message);
    } finally {
        await page.close();
    }
}

const result = Array.from(images.values());
writeFileSync(
    'storage/app/oranos-all-images.json',
    JSON.stringify({ scraped_at: new Date().toISOString(), images: result }, null, 2)
);

console.log('Total unique images: ' + result.length);
console.log('Pages crawled: ' + seenPages.size);
console.log('Output: storage/app/oranos-all-images.json');

await browser.close();