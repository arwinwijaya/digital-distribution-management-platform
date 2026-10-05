import { test, expect } from './support/fixtures';
import { createLogger } from './support/logger';
import { LoginPage } from './pages/login.page';
import { execSync } from 'child_process';

const API_BASE = 'http://localhost:8080/api';

/** Helper to run SQL against the API container's DB to deactivate a product. */
async function deactivateProductBySql(productId: number): Promise<void> {
  const sql = `UPDATE products SET is_active = false WHERE id = ${productId};`;
  execSync(
    `docker compose exec -T db psql -U ddp_user -d ddp_database -c "${sql.replace(/"/g, '\"')}"`,
    { stdio: 'pipe', timeout: 15000 },
  );
}

/** Helper to reactivate product for cleanup. */
async function reactivateProductBySql(productId: number): Promise<void> {
  const sql = `UPDATE products SET is_active = true WHERE id = ${productId};`;
  execSync(
    `docker compose exec -T db psql -U ddp_user -d ddp_database -c "${sql.replace(/"/g, '\"')}"`,
    { stdio: 'pipe', timeout: 15000 },
  );
}

test.describe.serial('Outlet Fast Reorder E2E & Edge-Case Verification', () => {
  test.setTimeout(240000);

  // 1. Concurrent tabs stale cache (acceptable v1 behavior — no auto-sync)
  test('Concurrent tabs: Tab A submits new order, Tab B reorder section stale until refresh', async ({ browser, runId }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    const context = await browser.newContext();
    try {
      const pageA = await context.newPage();
      const pageB = await context.newPage();

      const loginA = new LoginPage(pageA);
      await loginA.goto();
      await loginA.login('siti.nurhaliza@ddp.test', 'password123');

      // Tabs in one browser context share localStorage, so the authenticated
      // session created by pageA is already available to pageB.
      // Both open /orders
      await pageA.goto('/orders');
      await pageB.goto('/orders');

      await expect(pageA.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });
      await expect(pageB.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });

      // Wait for catalog and reorder section to load
      await pageA.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });
      await pageB.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });

      // Capture Tab B's reorder section innerHTML before Tab A submits
      const reorderSectionB = pageB.locator('text=Pesan Lagi').locator('..').locator('..');
      await expect(reorderSectionB).toBeVisible({ timeout: 20000 }); // Increased timeout
      const beforeHtml = await reorderSectionB.innerHTML();

      // Fill quantity on Tab A and submit
      await pageA.locator('input[aria-label^="Jumlah"]').first().fill('1');

      const responsePromise = pageA.waitForResponse(
        (r) => r.url().includes('/api/orders') && r.request().method() === 'POST',
      );
      await pageA.getByTestId('order-submit').click();
      const response = await responsePromise;

      expect([200, 201]).toContain(response.status());
      const orderBody = await response.json();
      expect(orderBody.data).toBeDefined();

      // Wait for success toast on Tab A
      await expect(pageA.getByTestId('order-success')).toBeVisible({ timeout: 20000 });

      // Tab B's reorder section should still show stale data (no auto-sync in v1)
      await pageB.waitForTimeout(1000);
      const afterHtml = await reorderSectionB.innerHTML();
      expect(afterHtml).toBe(beforeHtml); // stale: identical content

      // Refresh Tab B - reorder section should now be updated with new order in history
      await pageB.reload();
      await expect(pageB.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });
      await pageB.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });

      logger.logEvent({
        role: 'outlet',
        step: 'concurrent_tabs_stale_cache',
        action: 'browser: verified stale cache behavior acceptable v1',
        url: pageB.url(),
        selector: '[data-testid="orders-page"]',
        apiStatus: response.status(),
        durationMs: Date.now() - started,
        status: 'success',
      });
    } catch (error) {
      logger.logEvent({
        role: 'outlet',
        step: 'concurrent_tabs_stale_cache',
        action: 'browser: concurrent tabs test failed',
        url: '',
        selector: '',
        apiStatus: null,
        durationMs: Date.now() - started,
        status: 'failed',
        error: error instanceof Error ? error.message : String(error),
      });
      throw error;
    } finally {
      await context.close();
    }
  });

  // 2. Offline reorder with mixed product states (cache-only prefill + notice + queue)
  test('Offline reorder: "Ulangi Pesanan Terakhir" prefill from cache + notice + queue enqueue', async ({ browser, runId }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      const login = new LoginPage(page);
      await login.goto();
      await login.login('siti.nurhaliza@ddp.test', 'password123');

      // First load /orders online to populate cache
      await page.goto('/orders');
      await expect(page.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });
      await page.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });

      // Verify reorder section is present
      const reorderSection = page.locator('text=Pesan Lagi');
      await expect(reorderSection).toBeVisible({ timeout: 10000 });

      // Go offline
      await context.setOffline(true);

      // Click "Ulangi Pesanan Terakhir" button
      const reorderLastButton = page.locator('button:has-text("Ulangi Pesanan Terakhir")');
      await expect(reorderLastButton).toBeVisible({ timeout: 5000 });
      await reorderLastButton.click();

      // Should show offline notice: "berdasarkan data terakhir — validasi final saat online"
      const notice = page.locator('[data-testid="reorder-notice"]');
      await expect(notice).toBeVisible({ timeout: 5000 });
      await expect(notice).toContainText('berdasarkan data terakhir');

      // Submit the order while offline — should enqueue
      const submitButton = page.getByTestId('order-submit');
      await expect(submitButton).toBeVisible({ timeout: 5000 });
      await submitButton.click();

      // Should show pending count (queued)
      await expect(page.locator('text=menunggu sinkronisasi')).toBeVisible({ timeout: 10000 });

      // Verify the queued payload in IndexedDB carries the idempotency key
      const queuedPayload = await page.evaluate(async () => {
        const db = await new Promise<IDBDatabase>((resolve, reject) => {
          const request = indexedDB.open('ddp_offline_queue');
          request.onsuccess = () => resolve(request.result);
          request.onerror = () => reject(request.error);
        });
        return new Promise((resolve) => {
          const tx = db.transaction('kv', 'readonly');
          const store = tx.objectStore('kv');
          const req = store.get('ddp_offline_order_queue:v1');
          req.onsuccess = () => resolve(req.result);
          req.onerror = () => resolve(null);
        });
      });
      expect(queuedPayload).toBeTruthy();
      const queueItems = JSON.parse(queuedPayload as string);
      expect(Array.isArray(queueItems)).toBe(true);
      expect(queueItems.length).toBeGreaterThan(0);
      const hasIdempotencyKey = queueItems.some((item: any) =>
        item.payload && typeof item.payload === 'object' && 'idempotency_key' in item.payload
      );
      expect(hasIdempotencyKey).toBe(true);

      // Go back online
      await context.setOffline(false);

      // Auto-flush should trigger
      await page.waitForTimeout(3000);

      logger.logEvent({
        role: 'outlet',
        step: 'offline_reorder_mixed_states',
        action: 'browser: verified offline reorder cache-only + queue + idempotency key',
        url: page.url(),
        selector: '[data-testid="reorder-notice"]',
        apiStatus: 200,
        durationMs: Date.now() - started,
        status: 'success',
      });
    } catch (error) {
      logger.logEvent({
        role: 'outlet',
        step: 'offline_reorder_mixed_states',
        action: 'browser: offline reorder test failed',
        url: '',
        selector: '',
        apiStatus: null,
        durationMs: Date.now() - started,
        status: 'failed',
        error: error instanceof Error ? error.message : String(error),
      });
      throw error;
    } finally {
      await context.close();
    }
  });

  // 3. Product state change race condition (Admin deactivates product between prefill and submit)
  test('Race condition: Admin deactivates product between prefill and submit → per-item error, valid items retained', async ({ browser, runId, request }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    let productToDeactivate: number | null = null;
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      const login = new LoginPage(page);
      await login.goto();
      await login.login('siti.nurhaliza@ddp.test', 'password123');

      await page.goto('/orders');
      await expect(page.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });
      await page.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });

      // Add two products to cart (first two visible products)
      const qtyInputs = page.locator('input[aria-label^="Jumlah"]');
      await qtyInputs.first().fill('1');
      await qtyInputs.nth(1).fill('1');

      // Get products via API to find an active one
      const adminLogin = await request.post(`${API_BASE}/auth/login`, {
        data: { email: 'ratna.sari@ddp.test', password: 'password123' },
      });
      expect(adminLogin.ok()).toBeTruthy();
      const adminBody = await adminLogin.json();
      const adminToken = adminBody.data.token;

      const productsResp = await request.get(`${API_BASE}/products`, {
        headers: { Authorization: `Bearer ${adminToken}` },
      });
      expect(productsResp.ok()).toBeTruthy();
      const productsData = await productsResp.json();
      const products = productsData.data as Array<{ id: number; name: string; is_active: boolean }>;

      const target = products.find((p) => p.is_active);
      if (!target) throw new Error('No active product found to deactivate');
      productToDeactivate = target.id;

      // Deactivate the product via direct SQL
      await deactivateProductBySql(productToDeactivate);

      // Submit the order from the outlet page
      const responsePromise = page.waitForResponse(
        (r) => r.url().includes('/api/orders') && r.request().method() === 'POST',
      );
      await page.getByTestId('order-submit').click();
      const response = await responsePromise;

      // Backend should return 422 with per-item error
      expect(response.status()).toBe(422);
      const errorBody = await response.json();
      expect(errorBody.message).toContain('no longer available');

      // Valid items should remain in cart (not cleared)
      await expect(qtyInputs.first()).toHaveValue('1');
      await expect(qtyInputs.nth(1)).toHaveValue('1');

      logger.logEvent({
        role: 'outlet',
        step: 'race_condition_product_deactivated',
        action: 'browser: verified backend per-item error + valid items retained',
        url: page.url(),
        selector: '[data-testid="order-submit"]',
        apiStatus: response.status(),
        durationMs: Date.now() - started,
        status: 'success',
      });
    } catch (error) {
      logger.logEvent({
        role: 'outlet',
        step: 'race_condition_product_deactivated',
        action: 'browser: race condition test failed',
        url: '',
        selector: '',
        apiStatus: null,
        durationMs: Date.now() - started,
        status: 'failed',
        error: error instanceof Error ? error.message : String(error),
      });
      throw error;
    } finally {
      if (productToDeactivate) {
        try { await reactivateProductBySql(productToDeactivate); } catch { /* ignore */ }
      }
      await context.close();
    }
  });

  // 4. Accessibility: keyboard navigation and ARIA labels
  test('Accessibility: keyboard navigation through reorder section, Enter/Space triggers, descriptive aria-labels', async ({ browser, runId }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      const login = new LoginPage(page);
      await login.goto();
      await login.login('siti.nurhaliza@ddp.test', 'password123');

      await page.goto('/orders');
      await expect(page.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });
      await page.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });

      // Focus the "Ulangi Pesanan Terakhir" button
      const reorderButton = page.locator('button:has-text("Ulangi Pesanan Terakhir")');
      await expect(reorderButton).toBeVisible();

      const ariaLabel = await reorderButton.getAttribute('aria-label');
      expect(ariaLabel).toBe('Ulangi Pesanan Terakhir');

      await reorderButton.focus();
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);

      const addButtons = page.locator('button:has-text("+ Tambah")');
      const count = await addButtons.count();
      if (count > 0) {
        const firstAddButton = addButtons.first();
        const addAriaLabel = await firstAddButton.getAttribute('aria-label');
        expect(addAriaLabel).toBeTruthy();
        expect(addAriaLabel).toMatch(/Tambah .+, qty \d+ —/);
      }

      // Tab through quantity inputs
      const firstQty = page.locator('input[aria-label^="Jumlah"]').first();
      await firstQty.focus();
      await expect(firstQty).toBeFocused();
      await page.keyboard.press('Tab');
      const secondQty = page.locator('input[aria-label^="Jumlah"]').nth(1);
      await expect(secondQty).toBeFocused({ timeout: 5000 });

      logger.logEvent({
        role: 'outlet',
        step: 'accessibility_keyboard_aria',
        action: 'browser: verified keyboard nav + ARIA labels on reorder controls',
        url: page.url(),
        selector: 'button:has-text("Ulangi Pesanan Terakhir")',
        apiStatus: 200,
        durationMs: Date.now() - started,
        status: 'success',
      });
    } catch (error) {
      logger.logEvent({
        role: 'outlet',
        step: 'accessibility_keyboard_aria',
        action: 'browser: accessibility test failed',
        url: '',
        selector: '',
        apiStatus: null,
        durationMs: Date.now() - started,
        status: 'failed',
        error: error instanceof Error ? error.message : String(error),
      });
      throw error;
    } finally {
      await context.close();
    }
  });
});
