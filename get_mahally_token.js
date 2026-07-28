/**
 * Mahally Token Extractor v3
 * - Properly validates JWT (must have 2 dots: header.payload.sig)
 * - Dumps ALL localStorage keys to debug
 * - Waits for actual API calls to fire and intercepts Authorization header
 * - Triggers search to force API calls
 */

import { chromium } from 'playwright';

const isJWT = (str) => {
  if (!str || typeof str !== 'string') return false;
  const parts = str.split('.');
  return parts.length === 3 && parts[0].startsWith('eyJ') && parts[1].startsWith('eyJ');
};

(async () => {
  let token = null;

  const browser = await chromium.launch({
    headless: true,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-blink-features=AutomationControlled',
    ],
  });

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
    locale: 'ar-SA',
    viewport: { width: 1280, height: 720 },
    extraHTTPHeaders: { 'accept-language': 'ar-AE,ar;q=0.9' },
  });

  await context.addInitScript(() => {
    Object.defineProperty(navigator, 'webdriver', { get: () => false });
  });

  const page = await context.newPage();

  // Intercept requests - look for Bearer JWT
  page.on('request', (req) => {
    if (!req.url().includes('api.salla.dev')) return;
    const auth = req.headers()['authorization'] || '';
    if (auth.startsWith('Bearer ')) {
      const t = auth.replace('Bearer ', '').trim();
      if (isJWT(t) && !token) {
        token = t;
        process.stderr.write(`[✅] JWT captured from request to: ${req.url()}\n`);
      }
    }
  });

  // Intercept responses - look for JWT in body
  page.on('response', async (res) => {
    if (token) return;
    try {
      const body = await res.text();
      const matches = body.matchAll(/eyJ[A-Za-z0-9\-_]+\.eyJ[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+/g);
      for (const m of matches) {
        if (isJWT(m[0])) {
          token = m[0];
          process.stderr.write(`[✅] JWT found in response body: ${res.url()}\n`);
          break;
        }
      }
    } catch {}
  });

  try {
    // Load home page
    process.stderr.write('[INFO] Loading mahally.com/ar ...\n');
    await page.goto('https://mahally.com/ar', { waitUntil: 'domcontentloaded', timeout: 25000 });
    await page.waitForTimeout(3000);

    // Dump localStorage for debugging
    const storage = await page.evaluate(() => {
      const data = {};
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        data[k] = localStorage.getItem(k);
      }
      return data;
    });
    process.stderr.write(`[INFO] localStorage keys: ${Object.keys(storage).join(', ')}\n`);
    for (const [k, v] of Object.entries(storage)) {
      if (v && v.includes('eyJ')) {
        process.stderr.write(`[INFO] Possible JWT in localStorage[${k}]: ${v.substring(0, 80)}\n`);
        if (isJWT(v)) {
          token = v;
          process.stderr.write('[✅] Valid JWT found in localStorage!\n');
          break;
        }
      }
    }

    if (!token) {
      // Try triggering a search by navigating to browse with query
      process.stderr.write('[INFO] Navigating to browse page to trigger API calls...\n');
      await page.goto('https://mahally.com/ar/browse/?query=عطور', {
        waitUntil: 'domcontentloaded',
        timeout: 20000,
      });
      await page.waitForTimeout(2000);
      await page.evaluate(() => window.scrollBy(0, 500));
      await page.waitForTimeout(3000);
    }

    if (!token) {
      // Try using the search box
      process.stderr.write('[INFO] Looking for search input...\n');
      const searchInput = await page.$('input[type="search"], input[placeholder*="بحث"], input[placeholder*="search"], input[name="q"]');
      if (searchInput) {
        await searchInput.fill('عطور');
        await searchInput.press('Enter');
        process.stderr.write('[INFO] Search submitted, waiting for API calls...\n');
        await page.waitForTimeout(5000);
      }
    }

    if (!token) {
      // Try clicking any store category
      process.stderr.write('[INFO] Trying to click a category link...\n');
      try {
        await page.click('a[href*="browse"], a[href*="category"], a[href*="تسوق"]', { timeout: 3000 });
        await page.waitForTimeout(4000);
      } catch {}
    }

    // Final check - scan full page HTML
    if (!token) {
      process.stderr.write('[INFO] Scanning page HTML for JWT...\n');
      const html = await page.content();
      const matches = html.matchAll(/eyJ[A-Za-z0-9\-_]+\.eyJ[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+/g);
      for (const m of matches) {
        if (isJWT(m[0])) {
          token = m[0];
          process.stderr.write('[✅] JWT found in page HTML!\n');
          break;
        }
      }
    }

  } catch (err) {
    process.stderr.write(`[ERROR] ${err.message}\n`);
  } finally {
    await browser.close();
  }

  if (token) {
    process.stderr.write(`[SUCCESS] Token: ${token.substring(0, 50)}...\n`);
    process.stdout.write(token);
    process.exit(0);
  } else {
    process.stderr.write('[FAIL] No JWT found\n');
    process.stdout.write('ERROR:no_token');
    process.exit(1);
  }
})();
