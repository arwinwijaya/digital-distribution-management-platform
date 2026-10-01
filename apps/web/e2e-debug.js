const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.on('console', (m) => console.log('CONSOLE:', m.type(), m.text()));
  page.on('pageerror', (e) => console.log('PAGEERROR:', e.message));
  page.on('requestfailed', (r) => console.log('REQFAIL:', r.url(), r.failure()?.errorText));

  console.log('goto /login');
  await page.goto('http://localhost:8080/login', { waitUntil: 'domcontentloaded', timeout: 30000 });
  console.log('url:', page.url());
  console.log('has login-form-panel:', await page.locator('[data-testid="login-form-panel"]').count());
  console.log('has login-email:', await page.locator('[data-testid="login-email"]').count());
  await page.screenshot({ path: 'test-results/debug-login.png', fullPage: true });

  if (await page.locator('[data-testid="login-email"]').count()) {
    await page.getByTestId('login-email').fill('siti.nurhaliza@ddp.test');
    await page.getByTestId('login-password').fill('password123');
    await page.getByTestId('login-submit').click();
    await page.waitForTimeout(4000);
    console.log('after login url:', page.url());
    await page.screenshot({ path: 'test-results/debug-after-login.png', fullPage: true });
    console.log('body text:', (await page.locator('body').innerText()).slice(0, 500));
  }
  await browser.close();
})().catch((e) => { console.error('SCRIPT ERROR:', e); process.exit(1); });
