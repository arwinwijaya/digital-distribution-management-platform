const fs = require('fs');
const net = require('net');
const os = require('os');
const path = require('path');
const { execFileSync, spawn, spawnSync } = require('child_process');

jest.setTimeout(120000);

describe('real HTTP API order flow E2E', () => {
  let apiProcess;
  let apiUrl;
  let databasePath;
  let runtime;

  const php = process.env.PHP_BINARY || 'php';
  const apiDirectory = path.resolve(__dirname, '..', 'api');
  const routerPath = path.join(apiDirectory, 'tests', 'Support', 'http_server.php');

  function makeRuntime(database) {
    return {
      ...process.env,
      APP_ENV: 'testing',
      APP_KEY: `base64:${Buffer.alloc(32, 'a').toString('base64')}`,
      APP_URL: 'http://127.0.0.1',
      JWT_SECRET: Buffer.alloc(32, 'b').toString(),
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: database,
      CACHE_STORE: 'array',
      SESSION_DRIVER: 'array',
      QUEUE_CONNECTION: 'sync',
      TELESCOPE_ENABLED: 'false',
      LOG_CHANNEL: 'stderr',
    };
  }

  function wait(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
  }

  function findFreePort() {
    return new Promise((resolve, reject) => {
      const listener = net.createServer();
      listener.once('error', reject);
      listener.listen(0, '127.0.0.1', () => {
        const address = listener.address();
        listener.close(() => resolve(address.port));
      });
    });
  }

  async function waitForServer(url) {
    const deadline = Date.now() + 30000;
    let lastError;
    while (Date.now() < deadline) {
      try {
        const response = await fetch(`${url}/api/health`);
        if (response.ok) return;
      } catch (error) {
        lastError = error;
      }
      await wait(100);
    }
    throw new Error(`Laravel API server did not start: ${lastError || 'health check failed'}`);
  }

  function seedAdminAndProduct() {
    const seedPath = path.join(os.tmpdir(), `ddp-real-http-e2e-seed-${process.pid}.php`);
    const bootstrapPath = path.join(apiDirectory, 'bootstrap', 'app.php').replace(/\\/g, '/');
    const autoloadPath = path.join(apiDirectory, 'vendor', 'autoload.php').replace(/\\/g, '/');
    const seed = `<?php
require '${autoloadPath}';
$app = require '${bootstrapPath}';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
App\\Models\\User::create([
    'name' => 'E2E Admin',
    'email' => 'real-e2e-admin@example.com',
    'password' => Illuminate\\Support\\Facades\\Hash::make('admin-password'),
    'role' => 'admin',
    'is_active' => true,
]);
App\\Models\\Product::create([
    'name' => 'E2E Rice',
    'description' => 'Real HTTP test product',
    'price' => 10000,
    'sku' => 'E2E-RICE-001',
    'stock_quantity' => 20,
    'category' => 'food',
    'is_active' => true,
]);
`;
    fs.writeFileSync(seedPath, seed);
    try {
      execFileSync(php, [seedPath], { cwd: apiDirectory, env: runtime, stdio: 'pipe' });
    } finally {
      fs.rmSync(seedPath, { force: true });
    }
  }

  async function request(route, options = {}) {
    const response = await fetch(`${apiUrl}${route}`, {
      signal: AbortSignal.timeout(30000),
      ...options,
      headers: {
        Accept: 'application/json',
        Connection: 'close',
        ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        ...(options.headers || {}),
      },
    });
    const body = JSON.parse(await response.text());
    return { response, body };
  }

  function authHeaders(token, extra = {}) {
    return { Authorization: `Bearer ${token}`, ...extra };
  }

  beforeAll(async () => {
    const port = await findFreePort();
    databasePath = path.join(os.tmpdir(), `ddp-real-http-e2e-${process.pid}-${Date.now()}.sqlite`);
    fs.closeSync(fs.openSync(databasePath, 'w'));
    runtime = makeRuntime(databasePath);

    execFileSync(php, ['artisan', 'migrate:fresh', '--force'], {
      cwd: apiDirectory,
      env: runtime,
      stdio: 'pipe',
    });
    seedAdminAndProduct();

    // This is a separate PHP built-in-server process running Laravel's real HTTP kernel.
    apiProcess = spawn(php, ['-S', `127.0.0.1:${port}`, routerPath], {
      cwd: apiDirectory,
      env: runtime,
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    apiUrl = `http://127.0.0.1:${port}`;
    await waitForServer(apiUrl);
  });

  afterAll(async () => {
    if (apiProcess && apiProcess.exitCode === null) {
      if (process.platform === 'win32' && apiProcess.pid) {
        spawnSync('taskkill', ['/pid', String(apiProcess.pid), '/t', '/f'], { stdio: 'ignore' });
      } else {
        apiProcess.kill('SIGTERM');
      }
      await new Promise((resolve) => apiProcess.once('exit', resolve));
    }
    if (databasePath) fs.rmSync(databasePath, { force: true });
  });

  it('onboards, logs in, browses, submits idempotently, approves, and tracks over HTTP', async () => {
    const registration = await request('/api/auth/register', {
      method: 'POST',
      body: JSON.stringify({
        name: 'Real HTTP Outlet',
        email: 'real-e2e-outlet@example.com',
        password: 'outlet-password',
        password_confirmation: 'outlet-password',
        phone: '081234567899',
        address: 'Jl. Real HTTP',
        city: 'Jakarta',
        district: 'Menteng',
      }),
    });
    expect(registration.response.status).toBe(201);
    expect(registration.body.data.outlet.user_id).toBe(registration.body.data.user.id);

    const login = await request('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: 'real-e2e-outlet@example.com', password: 'outlet-password' }),
    });
    expect(login.response.status).toBe(200);
    const outletToken = login.body.data.token;

    const catalog = await request('/api/products', {
      headers: authHeaders(outletToken),
    });
    expect(catalog.response.status).toBe(200);
    expect(catalog.body.data).toHaveLength(1);
    const product = catalog.body.data[0];

    const idempotencyKey = 'real-http-order-attempt-001';
    const orderPayload = JSON.stringify({
      items: [{ product_id: product.id, quantity: 2 }],
      idempotency_key: idempotencyKey,
    });
    const firstSubmission = await request('/api/orders', {
      method: 'POST',
      headers: authHeaders(outletToken, { 'Idempotency-Key': idempotencyKey }),
      body: orderPayload,
    });
    const retrySubmission = await request('/api/orders', {
      method: 'POST',
      headers: authHeaders(outletToken, { 'Idempotency-Key': idempotencyKey }),
      body: orderPayload,
    });
    expect(firstSubmission.response.status).toBe(201);
    expect(retrySubmission.response.status).toBe(200);
    expect(retrySubmission.body.data.id).toBe(firstSubmission.body.data.id);
    expect(retrySubmission.body.data.order_id).toBe(firstSubmission.body.data.order_id);

    const adminLogin = await request('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: 'real-e2e-admin@example.com', password: 'admin-password' }),
    });
    expect(adminLogin.response.status).toBe(200);
    const adminToken = adminLogin.body.data.token;

    const adminOrders = await request('/api/admin/orders', {
      headers: authHeaders(adminToken),
    });
    expect(adminOrders.response.status).toBe(200);
    expect(adminOrders.body.data).toHaveLength(1);

    const orderId = firstSubmission.body.data.id;
    const approval = await request(`/api/orders/${orderId}/approve`, {
      method: 'PUT',
      headers: authHeaders(adminToken),
    });
    expect(approval.response.status).toBe(200);
    expect(approval.body.data.status).toBe('Confirmed');

    const tracking = await request(`/api/orders/${orderId}`, {
      headers: authHeaders(outletToken),
    });
    expect(tracking.response.status).toBe(200);
    expect(tracking.body.data.status).toBe('Confirmed');
    expect(tracking.body.data.status_history).toHaveLength(2);
  });
});
