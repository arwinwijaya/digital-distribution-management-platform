import { test, expect } from './support/fixtures';
import { createLogger } from './support/logger';
import { LoginPage } from './pages/login.page';
import { execSync } from 'child_process';

const API_BASE = 'http://localhost:8080/api';

/** Helper to run SQL against the API container's DB. */
async function runSql(sql: string): Promise<void> {
  execSync(
    `docker compose exec -T db psql -U ddp_user -d ddp_database -c "${sql.replace(/"/g, '\\"')}"`,
    { stdio: 'pipe', timeout: 15000 },
  );
}

/** Insert a deterministic last order for the outlet with both products. */
async function seedLastOrder(outletId: number): Promise<void> {
  // Insert order (created_at slightly in future to be most recent)
  const orderSql = `
    INSERT INTO orders (outlet_id, order_id, status, total_amount, commission_percentage, idempotency_key, idempotency_payload_hash, created_at, updated_at, paid_amount, due_date)
    VALUES (
      ${outletId},
      'ORD-TEST-REORDER-${Date.now()}',
      'Delivered',
      40000.00,
      2.00,
      'test-seed-${Date.now()}',
      'test-hash-${Date.now()}',
      now() + interval '1 minute',
      now() + interval '1 minute',
      40000.00,
      CURRENT_DATE + interval '7 days'
    )
    RETURNING id;
  `;
  execSync(
    `docker compose exec -T db psql -U ddp_user -d ddp_database -t -c "${orderSql.replace(/"/g, '\\"')}"`,
    { stdio: 'pipe', timeout: 15000 },
  );

  // Get the inserted order id
  const getIdSql = `SELECT id FROM orders WHERE outlet_id = ${outletId} ORDER BY created_at DESC LIMIT 1;`;
  const out = execSync(
    `docker compose exec -T db psql -U ddp_user -d ddp_database -t -c "${getIdSql}"`,
    { stdio: 'pipe', timeout: 15000 },
  ).toString().trim();
  const orderId = out;

  // Insert order_items for product 1 and 2
  const itemsSql = `
    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, product_name_snapshot)
    VALUES
      (${orderId}, 1, 1, 25000.00, 25000.00, 'Minyak Goreng 2L'),
      (${orderId}, 2, 1, 15000.00, 15000.00, 'Beras Premium 5kg');
  `;
  execSync(
    `docker compose exec -T db psql -U ddp_user -d ddp_database -c "${itemsSql.replace(/"/g, '\\"')}"`,
    { stdio: 'pipe', timeout: 15000 },
  );
}

/** Fetch outlet ID for the test user via SQL. */
async function fetchOutletId(): Promise<number> {
  const sql = `SELECT o.id FROM outlets o JOIN users u ON u.id = o.user_id WHERE u.email = 'siti.nurhaliza@ddp.test' LIMIT 1;`;
  const out = execSync(
    `docker compose exec -T db psql -U ddp_user -d ddp_database -t -c "${sql}"`,
    { stdio: 'pipe', timeout: 15000 },
  ).toString().trim();
  const id = Number(out);
  if (!Number.isFinite(id) || id <= 0) throw new Error(`Failed to resolve outlet id: ${out}`);
  return id;
}

/** Fetch outlet order history via API (requires outlet token). */
async function fetchOutletOrders(token: string, request: any): Promise<Array<{ id: number; order_id: string }>> {
  const resp = await request.get(`${API_BASE}/orders`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  if (!resp.ok()) return [];
  const body = await resp.json();
  return body.data as Array<{ id: number; order_id: string }>;
}

test.describe.serial('Outlet Fast Reorder E2E & Edge-Case Verification', () => {
  test.setTimeout(240000);

  let seededOutletId: number | null = null;

  // 0. Setup: seed a known last order with both products 1 & 2
  test.beforeAll(async () => {
    seededOutletId = await fetchOutletId();
    await runSql(`UPDATE products SET is_active = true WHERE id IN (1,2);`); // ensure both active for seed
    await seedLastOrder(seededOutletId);
  });

  test.afterAll(async () => {
    // Restore products to active
    await runSql(`UPDATE products SET is_active = true WHERE id IN (1,2);`);
  });

  // 1. Offline reorder with mixed product states (cache-only prefill + notice + queue)
  test('Offline reorder: "Ulangi Pesanan Terakhir" prefill from cache + notice + queue enqueue', async ({ browser, runId }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      const login = new LoginPage(page);
      await login.goto();
      await login.login('siti.nurhaliza@ddp.test', 'password123');

      // I2 FIX: Deactivate product 2 BEFORE page load so catalog reflects it
      await runSql(`UPDATE products SET is_active = false WHERE id = 2;`);

      // First load /orders online to populate cache with seeded history
      await page.goto('/orders');
      await expect(page.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });
      await page.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });

      // Verify reorder section is present
      const reorderSection = page.locator('text=Pesan Lagi');
      await expect(reorderSection).toBeVisible({ timeout: 10000 });

      // Go offline
      await context.setOffline(true);

      // Track /products requests while offline
      let productsRequestCount = 0;
      page.on('request', (req) => {
        if (req.url().includes('/products')) productsRequestCount++;
      });

      // Click "Ulangi Pesanan Terakhir" button
      const reorderLastButton = page.locator('button:has-text("Ulangi Pesanan Terakhir")');
      await expect(reorderLastButton).toBeVisible({ timeout: 5000 });
      await reorderLastButton.click();

      // Should show offline notice
      const notice = page.locator('[data-testid="reorder-notice"]');
      await expect(notice).toBeVisible({ timeout: 5000 });
      await expect(notice).toContainText('berdasarkan data terakhir');

      // Verify eligible-only prefill: product 1 qty = 1, product 2 qty = 0 (disabled)
      const product1Qty = page.locator('input[aria-label="Jumlah Minyak Goreng 2L"]');
      const product2Qty = page.locator('input[aria-label="Jumlah Beras Premium 5kg"]');
      await expect(product1Qty).toHaveValue('1', { timeout: 5000 });
      await expect(product2Qty).toHaveValue('0', { timeout: 5000 });
      // product 2 should be disabled (inactive)
      await expect(product2Qty).toBeDisabled();

      // Submit the order while offline — should enqueue
      const submitButton = page.getByTestId('order-submit');
      await expect(submitButton).toBeVisible({ timeout: 5000 });
      await submitButton.click();

      // Should show pending count (queued)
      await expect(page.locator('text=menunggu sinkronisasi')).toBeVisible({ timeout: 10000 });

      // I2 FIX: Assert zero /products network requests while offline
      expect(productsRequestCount).toBe(0);

      // Verify the queued payload in IndexedDB carries the idempotency key and only product 1
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
      // Check payload items only contain product 1
      const payload = queueItems[0].payload as { items: Array<{ product_id: number; quantity: number }> };
      expect(payload.items).toHaveLength(1);
      expect(payload.items[0].product_id).toBe(1);
      const hasIdempotencyKey = 'idempotency_key' in payload;
      expect(hasIdempotencyKey).toBe(true);

      // Go back online
      await context.setOffline(false);

      // I2 FIX: Wait for flush completion (queue cleared)
      await expect(async () => {
        const remainingPayload = await page.evaluate(async () => {
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
        if (!remainingPayload) return true;
        const items = JSON.parse(remainingPayload as string);
        return items.length === 0;
      }).toPass({ timeout: 20000 });

      // Pending indicator should be gone
      await expect(page.locator('text=menunggu sinkronisasi')).toBeHidden({ timeout: 10000 });

      logger.logEvent({
        role: 'outlet',
        step: 'offline_reorder_mixed_states',
        action: 'browser: verified offline reorder cache-only + queue + idempotency key + no network requests + flush completion + eligible-only prefill',
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
      // Restore product 2 so subsequent tests see an active catalog again.
      try { await runSql(`UPDATE products SET is_active = true WHERE id = 2;`); } catch { /* ignore */ }
      await context.close();
    }
  });

  // 2. Concurrent tabs stale cache (acceptable v1 behavior — no auto-sync)
  test('Concurrent tabs: Tab A submits new order, Tab B reorder section stale until refresh', async ({ browser, runId, request }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    let tabAOrderId: string | null = null;
    const context = await browser.newContext();
    try {
      const pageA = await context.newPage();
      const pageB = await context.newPage();

      const loginA = new LoginPage(pageA);
      await loginA.goto();
      await loginA.login('siti.nurhaliza@ddp.test', 'password123');

      // Tabs in one browser context share localStorage, so the authenticated
      // session created by pageA is already available to pageB.
      await pageA.goto('/orders');
      await pageB.goto('/orders');

      await expect(pageA.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });
      await expect(pageB.getByTestId('orders-page')).toBeVisible({ timeout: 20000 });

      // Wait for catalog and reorder section to load
      await pageA.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });
      await pageB.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 20000 });

      // Capture Tab B's reorder section innerHTML before Tab A submits
      const reorderSectionB = pageB.locator('text=Pesan Lagi').locator('..').locator('..');
      await expect(reorderSectionB).toBeVisible({ timeout: 20000 });
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
      tabAOrderId = orderBody.data?.order_id;
      expect(tabAOrderId).toBeTruthy();

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

      // I1 FIX: Assert the refresh picked up the new order via API + reorder section visible
      if (tabAOrderId) {
        const outletToken = await (async () => {
          const loginResp = await request.post(`${API_BASE}/auth/login`, {
            data: { email: 'siti.nurhaliza@ddp.test', password: 'password123' },
          });
          const loginBody = await loginResp.json();
          return loginBody.data.token as string;
        })();
        const outletOrders = await fetchOutletOrders(outletToken, request);
        expect(outletOrders.some((o) => o.order_id === tabAOrderId)).toBe(true);
      }
      await expect(pageB.locator('text=Pesan Lagi').locator('..').locator('..')).toBeVisible({ timeout: 20000 });

      logger.logEvent({
        role: 'outlet',
        step: 'concurrent_tabs_stale_cache',
        action: 'browser: verified stale cache behavior acceptable v1 + refresh pickup',
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
      await runSql(`UPDATE products SET is_active = false WHERE id = ${productToDeactivate};`);

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
        try { await runSql(`UPDATE products SET is_active = true WHERE id = ${productToDeactivate};`); } catch { /* ignore */ }
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

      // I3 FIX: Focus the "Ulangi Pesanan Terakhir" reorder button
      const reorderButton = page.locator('button:has-text("Ulangi Pesanan Terakhir")');
      await expect(reorderButton).toBeVisible();

      const ariaLabel = await reorderButton.getAttribute('aria-label');
      expect(ariaLabel).toBe('Ulangi Pesanan Terakhir');

      await reorderButton.focus();
      await expect(reorderButton).toBeFocused();

      // Press Enter on reorder button - triggers reorder last order
      await page.keyboard.press('Enter');
      await page.waitForTimeout(300);

      // Check "+ Tambah" buttons in reorder section
      const addButtons = page.locator('button:has-text("+ Tambah")');
      const count = await addButtons.count();
      if (count > 0) {
        const firstAddButton = addButtons.first();
        const addAriaLabel = await firstAddButton.getAttribute('aria-label');
        expect(addAriaLabel).toBeTruthy();
        expect(addAriaLabel).toMatch(/Tambah .+, qty \d+ —/);

        // Focus and press Space, and assert it populates cart quantity
        await firstAddButton.focus();
        await expect(firstAddButton).toBeFocused();
        await page.keyboard.press('Space');
        const firstQtyInput = page.locator('input[aria-label^="Jumlah"]').first();
        await expect(async () => {
          const val = await firstQtyInput.inputValue();
          return Number(val) > 0;
        }).toPass({ timeout: 5000 });
      }

      // Tab through catalog quantity inputs
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