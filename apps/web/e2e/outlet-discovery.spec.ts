import { test, expect } from './support/fixtures';
import { LoginPage } from './pages/login.page';
import { execSync } from 'child_process';

const API_BASE = 'http://localhost:8080/api';
const EMAIL = 'siti.nurhaliza@ddp.test';
const PASSWORD = 'password123';
const CATEGORY = 't9-discovery-e2e';
const SKU_PREFIX = 'T9-DISCOVERY-E2E-';
const ORDER_PREFIX = 'T9-DISCOVERY-SEED-';

type Seed = { outletId: number; purchased: number; candidates: number[]; names: string[] };

/** Execute fixed SQL through the existing compose E2E database. */
function sql(statement: string): string {
  const singleLine = statement.replace(/\s+/g, ' ').trim();
  return execSync('docker compose exec -T db psql -U ddp_user -d ddp_database -At -c ' + JSON.stringify(singleLine), {
    encoding: 'utf8', timeout: 15000,
  }).trim();
}

async function seedFixture(): Promise<Seed> {
  // This namespace is reserved exclusively for this suite. Cleanup is constrained
  // to rows carrying its SKU/order prefix and never touches shared fixture data.
  sql(`DELETE FROM recommendation_events WHERE product_id IN (SELECT id FROM products WHERE sku LIKE '${SKU_PREFIX}%');`);
  sql(`DELETE FROM orders WHERE idempotency_key LIKE '${ORDER_PREFIX}%';`);
  sql(`DELETE FROM products WHERE sku LIKE '${SKU_PREFIX}%';`);

  const outletId = Number(sql(`SELECT id FROM outlets WHERE user_id=(SELECT id FROM users WHERE email='${EMAIL}')`));
  if (!outletId) throw new Error('E2E fixture outlet not found');
  const inserted = sql(`INSERT INTO products (name,description,price,sku,stock_quantity,category,is_active,created_at,updated_at)
    VALUES ('T9 Discovery Purchased','E2E owned fixture',1000,'${SKU_PREFIX}PURCHASED',10,'${CATEGORY}',true,now(),now()),
           ('T9 Discovery Alpha','E2E owned fixture',1200,'${SKU_PREFIX}ALPHA',10,'${CATEGORY}',true,now(),now()),
           ('T9 Discovery Beta','E2E owned fixture',1300,'${SKU_PREFIX}BETA',10,'${CATEGORY}',true,now(),now()),
           ('T9 Discovery Gamma','E2E owned fixture',1400,'${SKU_PREFIX}GAMMA',10,'${CATEGORY}',true,now(),now());`);
  if (!inserted.includes('INSERT 0 4')) throw new Error(`Failed to insert discovery products: ${inserted}`);
  const rows = sql(`SELECT id FROM products WHERE sku LIKE '${SKU_PREFIX}%' ORDER BY CASE sku WHEN '${SKU_PREFIX}PURCHASED' THEN 1 WHEN '${SKU_PREFIX}ALPHA' THEN 2 WHEN '${SKU_PREFIX}BETA' THEN 3 ELSE 4 END`);
  const lines = rows.split('\n').filter(Boolean);
  const ids = lines.map((line) => Number(line.split('|')[0]));
  if (ids.length !== 4 || ids.some((id) => !id)) throw new Error(`Failed to seed discovery products: ${rows}`);
  // Seed a qualifying purchase to establish both discovery category and cached reorder data.
  sql(`INSERT INTO orders (outlet_id,order_id,status,total_amount,commission_percentage,idempotency_key,created_at,updated_at)
       VALUES (${outletId},'${ORDER_PREFIX}HISTORY','Delivered',1000,2,'${ORDER_PREFIX}HISTORY',now(),now());`);
  sql(`INSERT INTO order_items (order_id,product_id,quantity,unit_price,subtotal,created_at,updated_at)
       VALUES ((SELECT id FROM orders WHERE order_id='${ORDER_PREFIX}HISTORY'),${ids[0]},1,1000,1000,now(),now());`);
  return { outletId, purchased: ids[0], candidates: ids.slice(1), names: ['T9 Discovery Alpha', 'T9 Discovery Beta', 'T9 Discovery Gamma'] };
}

async function cleanupFixture(seed: Seed, createdOrders: string[]): Promise<void> {
  if (createdOrders.length) {
    const quoted = createdOrders.map((id) => `'${id.replace(/'/g, "''")}'`).join(',');
    sql(`DELETE FROM orders WHERE order_id IN (${quoted});`);
  }
  sql(`DELETE FROM recommendation_events WHERE outlet_id=${seed.outletId} AND product_id IN (${[seed.purchased, ...seed.candidates].join(',')});`);
  sql(`DELETE FROM orders WHERE idempotency_key LIKE '${ORDER_PREFIX}%';`);
  sql(`DELETE FROM products WHERE id IN (${[seed.purchased, ...seed.candidates].join(',')});`);
}

async function login(page: import('@playwright/test').Page): Promise<void> {
  const form = new LoginPage(page);
  await form.goto();
  await form.login(EMAIL, PASSWORD);
}

async function getToken(request: import('@playwright/test').APIRequestContext): Promise<string> {
  const response = await request.post(`${API_BASE}/auth/login`, { data: { email: EMAIL, password: PASSWORD } });
  expect(response.ok()).toBeTruthy();
  return (await response.json()).data.token as string;
}

async function discoveryIds(request: import('@playwright/test').APIRequestContext, token: string): Promise<number[]> {
  const response = await request.get(`${API_BASE}/ai/discovery`, { headers: { Authorization: `Bearer ${token}` } });
  expect(response.ok()).toBeTruthy();
  const body = await response.json();
  return (body.data?.recommendations ?? body.recommendations).map((item: { product_id: number }) => item.product_id);
}

test.describe.serial('Outlet recommendation discovery E2E', () => {
  test.setTimeout(240000);
  let seed: Seed;
  let createdOrders: string[] = [];

  test.beforeAll(async () => { seed = await seedFixture(); });
  test.afterEach(async () => {
    if (createdOrders.length) {
      const quoted = createdOrders.map((id) => `'${id.replace(/'/g, "''")}'`).join(',');
      sql(`DELETE FROM orders WHERE order_id IN (${quoted});`);
      createdOrders = [];
    }
  });
  test.afterAll(async () => { if (seed) await cleanupFixture(seed, createdOrders); });

  test('e2e_outlet_sees_discovery_adds_to_cart_submits_order', async ({ browser, request }) => {
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      await login(page);
      const token = await getToken(request);
      await page.goto('/orders');
      await expect(page.getByTestId('orders-page')).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Coba produk lain' })).toBeVisible();
      const item = page.locator('li').filter({ has: page.getByRole('heading', { name: seed.names[0], exact: true }) });
      await expect(item).toContainText('Populer di kategori t9-discovery-e2e');
      await item.getByRole('button', { name: /Tambah T9 Discovery Alpha/ }).click();
      await expect(page.getByText('Total pesanan').locator('..')).toContainText('Rp 1.200');
      const responsePromise = page.waitForResponse((r) => r.url().includes('/api/orders') && r.request().method() === 'POST');
      await page.getByTestId('order-submit').click();
      const response = await responsePromise;
      expect([200, 201]).toContain(response.status());
      expect(response.request().postDataJSON().items).toContainEqual({ product_id: seed.candidates[0], quantity: 1 });
      const order = (await response.json()).data;
      createdOrders.push(order.order_id);
      await expect(page.getByTestId('order-success')).toBeVisible();
      await expect.poll(() => discoveryIds(request, token)).not.toContain(seed.candidates[0]);
    } finally { await context.close(); }
  });

  test('e2e_offline_discovery_hidden_reorder_works', async ({ browser }) => {
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      await login(page);
      await page.goto('/orders');
      await expect(page.getByRole('heading', { name: 'Pesan Lagi' })).toBeVisible();
      await expect(page.getByRole('heading', { name: seed.names[0], exact: true })).toBeVisible();
      await context.setOffline(true);
      await page.evaluate(() => window.dispatchEvent(new Event('offline')));
      await expect(page.getByRole('heading', { name: 'Pesan Lagi' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Coba produk lain' })).toBeVisible();
      await expect(page.getByText(/tersedia saat online/i)).toBeVisible();
      await expect(page.getByRole('heading', { name: seed.names[0], exact: true })).toBeHidden();
      await context.setOffline(false);
      await page.evaluate(() => window.dispatchEvent(new Event('online')));
    } finally { await context.close(); }
  });

  test('e2e_tracking_events_recorded_displayed_clicked_cart', async ({ browser, request }) => {
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      await login(page);
      const token = await getToken(request);
      await page.goto('/orders');
      const discovery = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Coba produk lain' }) });
      for (const name of seed.names) await expect(discovery.getByRole('heading', { name, exact: true })).toBeVisible();
      const target = discovery.locator('li').filter({ has: page.getByRole('heading', { name: seed.names[1], exact: true }) });
      await target.getByRole('button', { name: /Tambah T9 Discovery Beta/ }).click();
      await expect.poll(async () => Number(sql(`SELECT count(*) FROM recommendation_events WHERE outlet_id=${seed.outletId} AND product_id IN (${seed.candidates.join(',')}) AND event_type='displayed'`))).toBeGreaterThanOrEqual(3);
      await expect.poll(async () => Number(sql(`SELECT count(*) FROM recommendation_events WHERE outlet_id=${seed.outletId} AND product_id=${seed.candidates[1]} AND event_type IN ('clicked','cart')`))).toBe(2);
      const events = sql(`SELECT event_type FROM recommendation_events WHERE outlet_id=${seed.outletId} AND product_id=${seed.candidates[1]} ORDER BY id`);
      expect(events.split('\n')).toEqual(expect.arrayContaining(['displayed', 'clicked', 'cart']));
      expect(await discoveryIds(request, token)).toContain(seed.candidates[1]); // cart-only dedup is client-session state
    } finally { await context.close(); }
  });

  test('e2e_stock_exhausted_between_add_and_submit_returns_422', async ({ browser }) => {
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      await login(page);
      await page.goto('/orders');
      const target = page.locator('li').filter({ has: page.getByRole('heading', { name: seed.names[2], exact: true }) });
      await target.getByRole('button', { name: /Tambah T9 Discovery Gamma/ }).click();
      await expect(page.getByText('Total pesanan').locator('..')).toContainText('Rp 1.400');
      sql(`UPDATE products SET stock_quantity=0 WHERE id=${seed.candidates[2]};`);
      const responsePromise = page.waitForResponse((r) => r.url().includes('/api/orders') && r.request().method() === 'POST');
      await page.getByTestId('order-submit').click();
      const response = await responsePromise;
      expect(response.status()).toBe(422);
      const body = await response.json();
      expect(JSON.stringify(body.errors ?? body.message)).toContain('items.0.quantity');
      expect(JSON.stringify(body.errors ?? body.message)).toContain('Insufficient stock');
      expect(sql(`SELECT count(*) FROM orders WHERE outlet_id=${seed.outletId} AND created_at > now() - interval '2 minutes' AND idempotency_key NOT LIKE '${ORDER_PREFIX}%'`)).toBe('0');
      await expect(page.getByTestId('order-success')).toBeHidden();
    } finally {
      sql(`UPDATE products SET stock_quantity=10 WHERE id=${seed.candidates[2]};`);
      await context.close();
    }
  });

  test('e2e_order_succeeds_when_tracking_fails', async ({ browser }) => {
    const context = await browser.newContext();
    try {
      const page = await context.newPage();
      await login(page);
      await page.route('**/api/ai/discovery/events', (route) => route.fulfill({ status: 500, body: '{"message":"tracking unavailable"}' }));
      await page.goto('/orders');
      const target = page.locator('li').filter({ has: page.getByRole('heading', { name: seed.names[0], exact: true }) });
      await target.getByRole('button', { name: /Tambah T9 Discovery Alpha/ }).click();
      const responsePromise = page.waitForResponse((r) => r.url().includes('/api/orders') && r.request().method() === 'POST');
      await page.getByTestId('order-submit').click();
      const response = await responsePromise;
      expect([200, 201]).toContain(response.status());
      const order = (await response.json()).data;
      createdOrders.push(order.order_id);
      await expect(page.getByTestId('order-success')).toBeVisible();
      await expect(page.getByRole('alert')).not.toContainText('tracking unavailable');
    } finally { await context.close(); }
  });
});
