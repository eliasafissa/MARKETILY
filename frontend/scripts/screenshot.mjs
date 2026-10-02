#!/usr/bin/env node
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Usage: node screenshot.mjs <url> <outputPath> <width> [height]
const [, , url, outputPath, widthStr, heightStr] = process.argv;

if (!url || !outputPath || !widthStr) {
  console.error('Usage: node screenshot.mjs <url> <outputPath> <width> [height]');
  process.exit(1);
}

const width = parseInt(widthStr, 10);
const height = heightStr ? parseInt(heightStr, 10) : Math.round(width * 0.75);

async function run() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({
    viewport: { width, height },
    deviceScaleFactor: 2,
  });

  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000); // wait for render
    await page.screenshot({ path: outputPath, fullPage: true });
    console.log(`Saved ${outputPath} (${width}x${height})`);
  } catch (err) {
    console.error('Screenshot failed:', err);
    process.exit(1);
  } finally {
    await browser.close();
  }
}

run();