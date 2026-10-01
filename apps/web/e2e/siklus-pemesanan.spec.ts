import { test, expect } from './support/fixtures';
import { createLogger } from './support/logger';
import { LoginPage } from './pages/login.page';

const API_BASE = 'http://localhost:8080/api';

// Module-scoped state shared across serial tests
let createdOrderId: number | null = null;
let createdDeliveryId: number | null = null;

test.describe.serial('Siklus pemesanan E2E (hybrid thin-UI)', () => {
  test.setTimeout(240000);

  test('Outlet creates order via browser', async ({ browser, runId }) => {
    test.setTimeout(300000);
    const logger = createLogger(runId);
    const started = Date.now();
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      const login = new LoginPage(page);
      await login.goto();
      await login.login('siti.nurhaliza@ddp.test', 'password123');

      await page.goto('/orders');
      await expect(page.getByTestId('orders-page')).toBeVisible();
      await expect(page.getByTestId('order-submit')).toBeVisible({ timeout: 15000 });

      await page.waitForSelector('input[aria-label^="Jumlah"]', { timeout: 15000 });
      const firstQty = page.locator('input[aria-label^="Jumlah"]').first();
      await firstQty.fill('2');
      const secondQty = page.locator('input[aria-label^="Jumlah"]').nth(1);
      await secondQty.fill('1');

      // Intercept the POST /api/orders response to get the created order ID
      const responsePromise = page.waitForResponse((r) => r.url().includes('/api/orders') && r.request().method() === 'POST');
      await page.getByTestId('order-submit').click();
      const response = await responsePromise;

      expect([200, 201]).toContain(response.status());
      const orderBody = await response.json();
      const createdOrder = orderBody.data;
      expect(createdOrder).toBeDefined();
      createdOrderId = createdOrder.id as number;

      const success = page.getByTestId('order-success');
      await expect(success).toBeVisible({ timeout: 15000 });
      const text = await success.textContent();
      expect(text).toMatch(/ORD-\S+/);

      logger.logEvent({
        role: 'outlet',
        step: 'order_created',
        action: 'browser: create order',
        url: page.url(),
        selector: '[data-testid="order-submit"]',
        apiStatus: response.status(),
        durationMs: Date.now() - started,
        status: 'success',
      });
    } catch (error) {
      logger.logEvent({
        role: 'outlet',
        step: 'order_created',
        action: 'browser: create order',
        url: '',
        selector: '[data-testid="order-submit"]',
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

  test('Admin approves and invoice available', async ({ browser, runId }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    expect(createdOrderId).not.toBeNull();
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      const login = new LoginPage(page);
      await login.goto();
      await login.login('ratna.sari@ddp.test', 'password123');

      await page.goto('/admin/orders');
      await expect(page.getByTestId('admin-orders-table')).toBeVisible({ timeout: 15000 });

      // Wait for the specific approve button for our order
      await page.waitForSelector(`[data-testid="admin-order-approve-${createdOrderId}"]`, { timeout: 15000 });
      await page.waitForSelector(`[data-testid="admin-order-approve-${createdOrderId}"]:enabled`, { timeout: 15000 });

      // Intercept approval response
      const approvePromise = page.waitForResponse((r) => r.url().includes(`/orders/${createdOrderId}/approve`) && r.request().method() === 'PUT');
      const approveButton = page.locator(`[data-testid="admin-order-approve-${createdOrderId}"]:enabled`);
      await approveButton.click();
      const approveResponse = await approvePromise;

      expect(approveResponse.ok()).toBeTruthy();
      const approveBody = await approveResponse.json();
      // Verify invoice generated and linked to the order
      expect(approveBody.data.invoice).toBeTruthy();
      expect(approveBody.data.status).toBe('Confirmed');

      logger.logEvent({
        role: 'admin',
        step: 'admin_approve',
        action: 'browser: approve order',
        url: page.url(),
        selector: `[data-testid="admin-order-approve-${createdOrderId}"]`,
        apiStatus: approveResponse.status(),
        durationMs: Date.now() - started,
        status: 'success',
      });
    } catch (error) {
      logger.logEvent({
        role: 'admin',
        step: 'admin_approve',
        action: 'browser: approve order',
        url: '',
        selector: `[data-testid="admin-order-approve-${createdOrderId}"]`,
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

  test('Admin assigns delivery', async ({ request, runId }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    expect(createdOrderId).not.toBeNull();
    const loginResp = await request.post(`${API_BASE}/auth/login`, {
      data: { email: 'ratna.sari@ddp.test', password: 'password123' },
    });
    expect(loginResp.ok()).toBeTruthy();
    const loginBody = await loginResp.json();
    const token = loginBody.data.token;

    // Find a driver
    const driversResp = await request.get(`${API_BASE}/users?role=driver`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    let driverId: number | undefined;
    if (driversResp.ok()) {
      const driversBody = await driversResp.json();
      driverId = driversBody.data?.[0]?.id;
    }
    if (!driverId) {
      const driverLogin = await request.post(`${API_BASE}/auth/login`, {
        data: { email: 'joko.widodo@ddp.test', password: 'password123' },
      });
      const driverBody = await driverLogin.json();
      driverId = driverBody.data?.user?.id;
    }
    expect(driverId).toBeDefined();

    // Assign delivery for our specific order
    const assignResp = await request.post(`${API_BASE}/deliveries`, {
      headers: { Authorization: `Bearer ${token}` },
      data: { order_id: createdOrderId, driver_id: driverId, notes: 'Pengiriman manual test 1 siklus pemesanan' },
    });
    expect([200, 201]).toContain(assignResp.status());
    const assignBody = await assignResp.json();
    createdDeliveryId = assignBody.data?.id as number;
    expect(createdDeliveryId).toBeDefined();

    logger.logEvent({
      role: 'admin',
      step: 'delivery_assigned',
      action: 'api: assign delivery',
      url: '/api/deliveries',
      selector: '',
      apiStatus: assignResp.status(),
      durationMs: Date.now() - started,
      status: 'success',
    });
  });

  test('Driver completes delivery', async ({ browser, runId, request }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    expect(createdDeliveryId).not.toBeNull();
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      const login = new LoginPage(page);
      await login.goto();
      await login.login('joko.widodo@ddp.test', 'password123');

      await page.goto('/delivery');
      await expect(page.locator(`[data-testid="delivery-start-${createdDeliveryId}"]`)).toBeVisible({ timeout: 15000 });
      await page.waitForSelector(`[data-testid="delivery-start-${createdDeliveryId}"]:enabled`, { timeout: 15000 });

      // Start delivery
      const startPromise = page.waitForResponse((r) => r.url().includes(`/deliveries/${createdDeliveryId}/status`) && (r.request().method() === 'PATCH' || r.request().method() === 'POST'));
      await page.locator(`[data-testid="delivery-start-${createdDeliveryId}"]:enabled`).click();
      const startResponse = await startPromise;
      expect(startResponse.ok()).toBeTruthy();

      // Wait for in_progress state - recipient and proof fields appear
      await expect(page.locator(`[data-testid="delivery-recipient-${createdDeliveryId}"]`)).toBeVisible({ timeout: 15000 });

      // Fill recipient and proof
      await page.locator(`[data-testid="delivery-recipient-${createdDeliveryId}"]`).fill('Siti Nurhaliza');
      await page.locator(`[data-testid="delivery-proof-url-${createdDeliveryId}"]`).fill('https://example.com/proof/manual-check-order-001.jpg');

      // Complete delivery
      const completePromise = page.waitForResponse((r) => r.url().includes(`/deliveries/${createdDeliveryId}/status`) && (r.request().method() === 'PATCH' || r.request().method() === 'POST'));
      await page.locator(`[data-testid="delivery-complete-${createdDeliveryId}"]`).click();
      const completeResponse = await completePromise;
      expect(completeResponse.ok()).toBeTruthy();

      logger.logEvent({
        role: 'driver',
        step: 'delivery_delivered',
        action: 'browser: complete delivery',
        url: page.url(),
        selector: `[data-testid="delivery-complete-${createdDeliveryId}"]`,
        apiStatus: completeResponse.status(),
        durationMs: Date.now() - started,
        status: 'success',
      });
    } catch (error) {
      logger.logEvent({
        role: 'driver',
        step: 'delivery_delivered',
        action: 'browser: complete delivery',
        url: '',
        selector: `[data-testid="delivery-complete-${createdDeliveryId}"]`,
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

  test('Finance pays in full → Paid', async ({ browser, runId, request }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    expect(createdOrderId).not.toBeNull();
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      const login = new LoginPage(page);
      await login.goto();
      await login.login('dewi.lestari@ddp.test', 'password123');

      await page.goto('/payments');
      await expect(page.getByTestId('payment-order-id')).toBeVisible({ timeout: 15000 });
      await page.getByTestId('payment-order-id').fill(String(createdOrderId));
      
      // Finance cannot read /orders/:id; admin can. Fetch total_amount from the admin detail API.
      const adminLogin = await request.post(`${API_BASE}/auth/login`, {
        data: { email: 'ratna.sari@ddp.test', password: 'password123' },
      });
      const adminBody = await adminLogin.json();
      const adminToken = adminBody.data.token;
      const orderResp = await request.get(`${API_BASE}/admin/orders/${createdOrderId}`, {
        headers: { Authorization: `Bearer ${adminToken}` },
      });
      expect(orderResp.ok()).toBeTruthy();
      const orderData = await orderResp.json();
      const totalAmount = orderData.data.total_amount;

      await page.getByTestId('payment-amount').fill(String(totalAmount));
      
      const paymentPromise = page.waitForResponse((r) => r.url().includes('/api/payments') && r.request().method() === 'POST');
      await page.getByTestId('payment-submit').click();
      const paymentResp = await paymentPromise;
      expect([200, 201]).toContain(paymentResp.status());
      const paymentBody = await paymentResp.json();
      expect(paymentBody.data).toBeDefined();
      // Verify order status is Paid after payment
      const statusCheck = await request.get(`${API_BASE}/orders/${createdOrderId}`, {
        headers: { Authorization: `Bearer ${adminToken}` },
      });
      expect(statusCheck.ok()).toBeTruthy();
      const statusData = await statusCheck.json();
      expect(statusData.data.status).toBe('Paid');
      await expect(page.getByTestId('payment-success')).toBeVisible({ timeout: 15000 });

      logger.logEvent({
        role: 'finance',
        step: 'payment_completed',
        action: 'browser: record payment',
        url: page.url(),
        selector: '[data-testid="payment-submit"]',
        apiStatus: paymentResp.status(),
        durationMs: Date.now() - started,
        status: 'success',
      });
    } catch (error) {
      logger.logEvent({
        role: 'finance',
        step: 'payment_completed',
        action: 'browser: record payment',
        url: '',
        selector: '[data-testid="payment-submit"]',
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

  test('Status history invariant', async ({ request, runId }) => {
    const logger = createLogger(runId);
    const started = Date.now();
    expect(createdOrderId).not.toBeNull();
    const adminLogin = await request.post(`${API_BASE}/auth/login`, {
      data: { email: 'ratna.sari@ddp.test', password: 'password123' },
    });
    const adminBody = await adminLogin.json();
    const adminToken = adminBody.data.token;

    const detailResp = await request.get(`${API_BASE}/orders/${createdOrderId}`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    expect(detailResp.ok()).toBeTruthy();
    const detailBody = await detailResp.json();
    const history = (detailBody.data.status_history || []).map((h: any) => h.status);
    expect(history).toEqual(expect.arrayContaining(['New', 'Confirmed', 'Delivered', 'Paid']));
    const idx = (s: string) => history.indexOf(s);
    expect(idx('New')).toBeLessThan(idx('Confirmed'));
    expect(idx('Confirmed')).toBeLessThan(idx('Delivered'));
    expect(idx('Delivered')).toBeLessThan(idx('Paid'));

    logger.logEvent({
      role: 'admin',
      step: 'history_verified',
      action: 'api: verify status history',
      url: `/api/orders/${createdOrderId}`,
      selector: '',
      apiStatus: detailResp.status(),
      durationMs: Date.now() - started,
      status: 'success',
    });
  });
});