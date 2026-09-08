<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $token;
    protected ?string $raceDatabase = null;
    protected ?string $raceBarrierDirectory = null;
    protected array $raceServerProcesses = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create outlet user and authenticate
        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'outlet@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'outlet@ddp.com',
            'password' => 'password123',
        ]);

        $this->token = $loginResponse->json('data.token');
    }

    protected function tearDown(): void
    {
        $this->stopRaceInfrastructure();
        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ============================================================
    // RED CYCLE 1: Order Creation
    // ============================================================

    /**
     * Test: Given outlet has products in cart, When placing order,
     * Then order is created with status `New`
     */
    public function test_outlet_can_create_order_with_products(): void
    {
        // Arrange: Create products
        $product1 = Product::factory()->create([
            'price' => 35000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $product2 = Product::factory()->create([
            'price' => 40000,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        // Act: POST /api/orders
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product1->id, 'quantity' => 10],
                    ['product_id' => $product2->id, 'quantity' => 5],
                ],
            ]);

        // Assert: Order is created with status New
        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'order_id',
                    'status',
                    'total_amount',
                    'items' => [
                        '*' => ['product_id', 'quantity', 'unit_price', 'subtotal'],
                    ],
                    'created_at',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'New',
                ],
            ]);

        // Verify order exists in database
        $this->assertDatabaseHas('orders', [
            'outlet_id' => $this->outlet->id,
            'status' => 'New',
        ]);

        // Verify order items exist
        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product1->id,
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product2->id,
            'quantity' => 5,
        ]);
    }

    /**
     * Test: Given missing items, When placing order, Then validation error
     */
    public function test_order_requires_items(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    /**
     * Test: Given invalid product_id, When placing order, Then validation error
     */
    public function test_order_rejects_invalid_product(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => 99999, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    /**
     * Test: Given invalid quantity, When placing order, Then validation error
     */
    public function test_order_rejects_invalid_quantity(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 0],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    /**
     * Test: Given unauthenticated request, When placing order, Then 401
     */
    public function test_unauthenticated_user_cannot_create_order(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(401);
    }

    // ============================================================
    // RED CYCLE 2: Order Approval
    // ============================================================

    /**
     * Test: Given order exists with status `New`, When admin approves,
     * Then status updates to `Confirmed`
     */
    public function test_admin_can_approve_new_order(): void
    {
        // Arrange: Create admin user and authenticate
        $adminUser = User::factory()->admin()->create([
            'email' => 'admin@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@ddp.com',
            'password' => 'password123',
        ]);
        $adminToken = $loginResponse->json('data.token');

        // Arrange: Create an order with status New
        $product = Product::factory()->create(['price' => 50000, 'is_active' => true]);

        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $orderId = $createResponse->json('data.id');

        // Act: PUT /api/orders/{id}/approve as admin
        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/orders/{$orderId}/approve");

        // Assert: Order status is Confirmed
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $orderId,
                    'status' => 'Confirmed',
                ],
            ]);

        // Verify in database
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'Confirmed',
        ]);
    }

    /**
     * Test: Given non-admin tries to approve, When approving, Then 403
     */
    public function test_outlet_cannot_approve_order(): void
    {
        // Create an order as outlet
        $product = Product::factory()->create(['price' => 50000, 'is_active' => true]);

        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $orderId = $createResponse->json('data.id');

        // Act: Try to approve as outlet (non-admin)
        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/orders/{$orderId}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test: Given order already confirmed, When approving again, Then error
     */
    public function test_cannot_approve_already_confirmed_order(): void
    {
        // Arrange: Create admin
        $adminUser = User::factory()->admin()->create([
            'email' => 'admin@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@ddp.com',
            'password' => 'password123',
        ]);
        $adminToken = $loginResponse->json('data.token');

        // Create order and approve it
        $product = Product::factory()->create(['price' => 50000, 'is_active' => true]);

        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $orderId = $createResponse->json('data.id');

        // Approve once
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/orders/{$orderId}/approve")
            ->assertStatus(200);

        // Act: Try to approve again
        $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/orders/{$orderId}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    // ============================================================
    // Supplemental: Idempotency
    // ============================================================

    /**
     * Test: Given identical order submission is repeated, When POST /api/orders
     * is received twice with the same idempotency key, Then exactly one order
     * is persisted and the same order identity/result is returned.
     */
    public function test_duplicate_order_submission_is_idempotent(): void
    {
        // Arrange: Create products
        $product = Product::factory()->create([
            'price' => 25000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $orderPayload = [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
            'idempotency_key' => 'test-idempotent-key-001',
        ];

        // Act: Submit the same order twice
        $response1 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $orderPayload);
        $response1->assertStatus(201);

        $response2 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $orderPayload);
        $response2->assertStatus(200); // 200 for returning existing, not 201

        // Assert: Same order_id returned in both responses
        $this->assertEquals(
            $response1->json('data.order_id'),
            $response2->json('data.order_id')
        );

        // Assert: Only one order persisted
        $this->assertCount(1, \App\Models\Order::all());
    }

    // ============================================================
    // Supplemental: Status History
    // ============================================================

    /**
     * Test: Given an order transitions through statuses, When the order is
     * fetched/tracked, Then status history records the transitions with
     * timestamps and current status is correct.
     */
    public function test_order_status_history_is_tracked(): void
    {
        // Arrange: Create admin
        $adminUser = User::factory()->admin()->create([
            'email' => 'admin@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@ddp.com',
            'password' => 'password123',
        ]);
        $adminToken = $loginResponse->json('data.token');

        // Create an order
        $product = Product::factory()->create(['price' => 50000, 'is_active' => true]);

        $createResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $orderId = $createResponse->json('data.id');

        // Approve the order
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson("/api/orders/{$orderId}/approve")
            ->assertStatus(200);

        // Act: Fetch the order with status history
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/orders/{$orderId}");

        // Assert: Order has status history
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'order_id',
                    'status',
                    'status_history' => [
                        '*' => ['status', 'created_at'],
                    ],
                ],
            ]);

        // Verify history contains both New and Confirmed
        $history = $response->json('data.status_history');
        $this->assertCount(2, $history);
        $this->assertEquals('New', $history[0]['status']);
        $this->assertEquals('Confirmed', $history[1]['status']);

        // Verify current status is Confirmed
        $this->assertEquals('Confirmed', $response->json('data.status'));
    }

    public function test_order_submission_snapshots_commission_and_decrements_stock(): void
    {
        $product = Product::factory()->create([
            'price' => 10000,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.commission_percentage', '2.00');
        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'commission_percentage' => 2.00,
            'total_amount' => 30000,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 5,
        ]);
    }

    public function test_unavailable_product_does_not_create_partial_order(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 1,
            'is_active' => false,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);

        $this->assertCount(0, Order::all());
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 1,
        ]);
    }

    public function test_duplicate_product_ids_are_rejected(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.1.product_id']);

        $this->assertCount(0, Order::all());
    }

    public function test_missing_request_key_uses_stable_identity_for_retries(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 2]]];

        $first = $this->withHeaders($this->authHeaders())->postJson('/api/orders', $payload);
        $second = $this->withHeaders($this->authHeaders())->postJson('/api/orders', $payload);

        $first->assertStatus(201);
        $second->assertStatus(200);
        $this->assertSame($first->json('data.order_id'), $second->json('data.order_id'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 8]);
        $this->assertCount(1, Order::all());
    }

    /**
     * Two independent PHP HTTP workers are released together inside the order
     * transaction, immediately before the idempotency lookup and product lock.
     * The file-backed SQLite database is shared by both Laravel servers; SQLite
     * serializes the eventual write, so the test asserts the retry/read-back
     * result rather than claiming that SQLite provides simultaneous row locks.
     */
    public function test_concurrent_same_identity_submissions_return_one_order_result(): void
    {
        $this->prepareRaceDatabase();
        [$outletUser, $outlet] = $this->createRaceOutlet();
        $product = $this->createRaceProduct([
            'price' => 25000,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);
        $this->startRaceServers('order');
        $token = $this->raceLogin($outletUser->email);
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'idempotency_key' => 'concurrent-order-key',
        ];

        $responses = $this->runConcurrentHttpRequests([
            ['token' => $token, 'body' => $payload],
            ['token' => $token, 'body' => $payload],
        ], '/api/orders');
        $statuses = array_column($responses, 'status');
        sort($statuses);

        $this->assertSame([200, 201], $statuses);
        $this->assertSame(
            $responses[0]['json']['data']['order_id'],
            $responses[1]['json']['data']['order_id']
        );
        $this->assertSame(1, Order::on('race')->where('outlet_id', $outlet->id)->count());
        $this->assertSame(1, OrderItem::on('race')->count());
        $this->assertSame(5, (int) Product::on('race')->findOrFail($product->id)->stock_quantity);
    }

    /**
     * Two independent public approval requests are released together inside
     * the approval transaction, immediately before lockForUpdate(). SQLite's
     * file lock serializes the write; one request therefore retries, observes
     * Confirmed, and returns the documented 422 conflict.
     */
    public function test_concurrent_admin_approvals_append_one_confirmed_history(): void
    {
        $this->prepareRaceDatabase();
        [, $outlet] = $this->createRaceOutlet();
        $product = $this->createRaceProduct(['price' => 50000, 'stock_quantity' => 5]);
        $order = Order::on('race')->create([
            'order_id' => 'ORD-RACE-APPROVAL',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 50000,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'race-approval-order',
        ]);
        OrderItem::on('race')->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'subtotal' => 50000,
        ]);
        OrderStatusHistory::on('race')->create([
            'order_id' => $order->id,
            'status' => 'New',
            'notes' => 'Order created',
        ]);
        $admin = $this->createRaceUser('concurrent-admin@ddp.com', 'admin');
        $this->startRaceServers('approval');
        $token = $this->raceLogin($admin->email);

        $responses = $this->runConcurrentHttpRequests([
            ['token' => $token, 'body' => []],
            ['token' => $token, 'body' => []],
        ], "/api/orders/{$order->id}/approve", 'PUT');
        $statuses = array_column($responses, 'status');
        sort($statuses);

        $this->assertSame([200, 422], $statuses);
        $this->assertSame('Confirmed', Order::on('race')->findOrFail($order->id)->status);
        $this->assertSame(1, OrderStatusHistory::on('race')
            ->where('order_id', $order->id)
            ->where('status', 'Confirmed')
            ->count());
    }

    public function test_admin_without_outlet_can_list_and_view_orders(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $orderResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $orderResponse->json('data.id');

        $admin = User::factory()->admin()->create([
            'email' => 'admin-no-outlet@ddp.com',
            'password' => Hash::make('password123'),
        ]);
        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);
        $headers = ['Authorization' => 'Bearer '.$login->json('data.token')];

        $this->withHeaders($headers)->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $orderId);
        $this->withHeaders($headers)->getJson('/api/admin/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);
    }

    private function prepareRaceDatabase(): void
    {
        $directory = storage_path('framework/testing');
        foreach (['views', 'cache', 'sessions'] as $subdirectory) {
            $path = storage_path('framework/'.$subdirectory);
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $this->raceDatabase = tempnam($directory, 'order-race-');
        if ($this->raceDatabase === false) {
            throw new \RuntimeException('Unable to create the race database file.');
        }

        config(['database.connections.race' => array_merge(
            config('database.connections.sqlite'),
            ['database' => $this->raceDatabase],
        )]);
        \DB::purge('race');
        $this->artisan('migrate:fresh', ['--database' => 'race', '--force' => true]);
    }

    private function createRaceUser(string $email, string $role = 'outlet'): User
    {
        $user = User::factory()->state(['role' => $role])->make([
            'email' => $email,
            'password' => Hash::make('password123'),
        ]);
        $user->setConnection('race');
        $user->save();

        return $user;
    }

    /** @return array{0: User, 1: Outlet} */
    private function createRaceOutlet(): array
    {
        $user = $this->createRaceUser('race-outlet@ddp.com');
        $outlet = Outlet::factory()->make([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $outlet->setConnection('race');
        $outlet->save();

        return [$user, $outlet];
    }

    private function createRaceProduct(array $attributes = []): Product
    {
        $product = Product::factory()->make($attributes);
        $product->setConnection('race');
        $product->save();

        return $product;
    }

    private function startRaceServers(string $section): void
    {
        $this->raceBarrierDirectory = storage_path('framework/testing/order-barrier-'.bin2hex(random_bytes(8)));
        mkdir($this->raceBarrierDirectory, 0777, true);
        $serverRouter = base_path('tests/Support/http_server.php');

        for ($index = 0; $index < 2; $index++) {
            $participant = $index === 0 ? 'A' : 'B';
            $port = $this->findFreePort();
            $log = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'server-'.$participant.'.log';
            $command = [PHP_BINARY, '-S', '127.0.0.1:'.$port, $serverRouter];
            $environment = getenv();
            $environment['APP_ENV'] = 'testing';
            $environment['APP_KEY'] = (string) config('app.key');
            $environment['DB_CONNECTION'] = 'sqlite';
            $environment['DB_DATABASE'] = $this->raceDatabase;
            $environment['CACHE_STORE'] = 'array';
            $environment['CACHE_DRIVER'] = 'array';
            $environment['SESSION_DRIVER'] = 'array';
            $environment['QUEUE_CONNECTION'] = 'sync';
            $environment['MAIL_MAILER'] = 'array';
            $environment['TELESCOPE_ENABLED'] = 'false';
            $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->raceBarrierDirectory;
            $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = $section;
            $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;

            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'ab'],
                2 => ['file', $log, 'ab'],
            ], $pipes, base_path(), $environment);
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start the Laravel race server.');
            }
            fclose($pipes[0]);

            $this->raceServerProcesses[] = [
                'process' => $process,
                'port' => $port,
                'log' => $log,
            ];
            $this->waitForRaceServer($port, $log);
        }
    }

    private function waitForRaceServer(int $port, string $log): void
    {
        $context = stream_context_create(['http' => [
            'timeout' => 0.25,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ]]);
        $url = "http://127.0.0.1:{$port}/api/health";
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (@file_get_contents($url, false, $context) !== false) {
                return;
            }
            usleep(50000);
        }

        $details = is_file($log) ? file_get_contents($log) : '';
        throw new \RuntimeException("Race server did not start: {$details}");
    }

    private function raceLogin(string $email): string
    {
        $response = $this->publicHttpRequest(
            'POST',
            $this->raceUrl(0).'/api/auth/login',
            ['email' => $email, 'password' => 'password123'],
        );
        if (($response['status'] ?? 0) !== 200 || ! isset($response['json']['data']['token'])) {
            throw new \RuntimeException('Race server login failed: '.json_encode($response));
        }

        return $response['json']['data']['token'];
    }

    /** @param array<int, array{token: string, body: array<string, mixed>}> $requests */
    private function runConcurrentHttpRequests(array $requests, string $path, string $method = 'POST'): array
    {
        $worker = base_path('tests/Support/http_request.php');
        $processes = [];
        $files = [];
        foreach ($requests as $index => $request) {
            $input = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            file_put_contents($input, json_encode([
                'method' => $method,
                'url' => $this->raceUrl($index).$path,
                'headers' => ['Authorization' => 'Bearer '.$request['token']],
                'body' => json_encode($request['body'], JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR));
            $command = [PHP_BINARY, $worker, $input, $output];
            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, base_path());
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start an HTTP worker.');
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            $processes[$index] = $process;
            $files[$index] = [$input, $output];
        }

        foreach ($processes as $process) {
            proc_close($process);
        }

        $responses = [];
        foreach ($files as $index => [$input, $output]) {
            if (! is_file($output)) {
                throw new \RuntimeException("HTTP worker {$index} produced no response.");
            }
            $raw = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            $raw['json'] = json_decode($raw['body'] ?? '', true);
            $responses[$index] = $raw;
            @unlink($input);
            @unlink($output);
        }

        return $responses;
    }

    /** @return array{status: int, body: string, json: array<string, mixed>|null} */
    private function publicHttpRequest(string $method, string $url, array $body): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
            'content' => json_encode($body, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $responseBody = file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }

        return [
            'status' => $status,
            'body' => $responseBody === false ? '' : $responseBody,
            'json' => json_decode($responseBody ?: '', true),
        ];
    }

    private function raceUrl(int $index): string
    {
        return 'http://127.0.0.1:'.$this->raceServerProcesses[$index]['port'];
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException("Unable to reserve a local port: {$error}");
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr(strrchr($name, ':'), 1);
    }

    private function stopRaceInfrastructure(): void
    {
        foreach ($this->raceServerProcesses as $server) {
            if (is_resource($server['process'])) {
                $status = proc_get_status($server['process']);
                if ($status['running']) {
                    proc_terminate($server['process']);
                }
                proc_close($server['process']);
            }
        }
        $this->raceServerProcesses = [];

        if ($this->raceBarrierDirectory && is_dir($this->raceBarrierDirectory)) {
            foreach (glob($this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->raceBarrierDirectory);
        }
        $this->raceBarrierDirectory = null;

        if ($this->raceDatabase) {
            \DB::purge('race');
            @unlink($this->raceDatabase);
            $this->raceDatabase = null;
        }
    }
}
