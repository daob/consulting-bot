/* Drive the real app in a real browser and photograph each state.
   node tools/screenshots.js [baseUrl] */
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://127.0.0.1:8080';
const OUT  = process.env.SHOTS_DIR || 'shots';
const CODE = process.env.CLASS_CODE || 'dev';

require('fs').mkdirSync(OUT, { recursive: true });

(async () => {
  const browser = await chromium.launch();
  const shot = async (page, name) => {
    await page.screenshot({ path: `${OUT}/${name}.png`, fullPage: true });
    console.log('  captured', name);
  };

  for (const scheme of ['light', 'dark']) {
    const ctx  = await browser.newContext({ viewport: { width: 900, height: 1000 }, colorScheme: scheme });
    const page = await ctx.newPage();
    page.on('console', (m) => m.type() === 'error' && console.log('  console error:', m.text()));
    page.on('pageerror', (e) => console.log('  PAGE ERROR:', e.message));

    console.log(scheme + ':');
    await page.goto(BASE + '/?s=%7BOrgDefinedId%7D', { waitUntil: 'networkidle' });
    await shot(page, `1-gate-${scheme}`);

    // The Brightspace replace string did not resolve; the field must be empty.
    const prefilled = await page.inputValue('#student');
    console.log('  unresolved replace string leaves the field:', JSON.stringify(prefilled));

    await page.goto(BASE + '/?s=1234567', { waitUntil: 'networkidle' });
    console.log('  resolved replace string prefills:', JSON.stringify(await page.inputValue('#student')));

    await page.fill('#code', CODE);
    await page.click('#gate-go');
    await page.waitForSelector('#chat:not([hidden])', { timeout: 20000 });

    await page.fill('#say', 'Come in, have a seat. What brings you here today?');
    await page.click('#send');
    await page.waitForSelector('.turn.client', { timeout: 90000 });
    await page.waitForTimeout(400);

    await page.fill('#say', 'Tell me more about how the data were collected — who visited the children, and when?');
    await page.click('#send');
    await page.waitForFunction(() => document.querySelectorAll('.turn.client').length >= 2, null, { timeout: 90000 });
    await page.waitForTimeout(400);
    await shot(page, `2-chat-${scheme}`);

    if (scheme === 'light') {
      page.once('dialog', (d) => d.accept());
      await page.click('#finish');
      await page.waitForSelector('#report:not([hidden])', { timeout: 180000 });
      await page.waitForTimeout(400);
      await shot(page, '3-report-light');
      const headings = await page.$$eval('#report-body h2, #report-body h3', (n) => n.map((e) => e.textContent));
      console.log('  report sections:', headings.join(' | '));
    }
    await ctx.close();
  }
  await browser.close();
  console.log('done');
})();
