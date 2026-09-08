<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $token;

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
     * SQLite's in-memory test driver cannot share a lockable database between
     * parallel PHP workers. Instead, the second public HTTP request is started
     * from the first request's validation query. This deterministic boundary
     * interleaving still exercises the same endpoint, idempotency lookup,
     * product lock, stock reservation, and read-back behavior.
     */
    public function test_concurrent_same_identity_submissions_return_one_order_result(): void
    {
        $product = Product::factory()->create([
            'price' => 25000,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'idempotency_key' => 'concurrent-order-key',
        ];
        $responses = [];
        $nestedStarted = false;

        DB::listen(function (QueryExecuted $query) use (&$nestedStarted, &$responses, $payload): void {
            $sql = strtolower($query->sql);
            if (!$nestedStarted && str_contains($sql, 'select count(*) as aggregate') && str_contains($sql, 'products')) {
                $nestedStarted = true;
                $responses['nested'] = $this->withHeaders($this->authHeaders())
                    ->postJson('/api/orders', $payload);
            }
        });

        $responses['outer'] = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', $payload);

        $this->assertTrue($nestedStarted, 'The deterministic concurrent HTTP request was not started.');
        $responses['nested']->assertSuccessful();
        $responses['outer']->assertSuccessful();
        $this->assertSame(
            $responses['nested']->json('data.order_id'),
            $responses['outer']->json('data.order_id')
        );
        $this->assertSame(1, Order::count());
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 5,
        ]);
    }

    /**
     * The SQLite in-memory driver serializes transactions, so this test
     * deterministically interleaves two public approval requests at the auth
     * boundary. The first request completes the locked transition; the second
     * observes Confirmed and cannot append another history record.
     */
    public function test_concurrent_admin_approvals_append_one_confirmed_history(): void
    {
        $product = Product::factory()->create(['price' => 50000, 'stock_quantity' => 5]);
        $orderResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertCreated();
        $orderId = $orderResponse->json('data.id');

        $admin = User::factory()->admin()->create([
            'email' => 'concurrent-admin@ddp.com',
            'password' => Hash::make('password123'),
        ]);
        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);
        $headers = ['Authorization' => 'Bearer '.$login->json('data.token')];
        $responses = [];
        $nestedStarted = false;

        DB::listen(function (QueryExecuted $query) use (&$nestedStarted, &$responses, $headers, $orderId): void {
            $sql = strtolower($query->sql);
            if (!$nestedStarted && str_contains($sql, 'from "users"') && str_contains($sql, 'limit 1')) {
                $nestedStarted = true;
                $responses['nested'] = $this->withHeaders($headers)
                    ->putJson("/api/orders/{$orderId}/approve");
            }
        });

        $responses['outer'] = $this->withHeaders($headers)
            ->putJson("/api/orders/{$orderId}/approve");

        $this->assertTrue($nestedStarted, 'The deterministic concurrent approval request was not started.');
        $this->assertContains(200, [$responses['nested']->status(), $responses['outer']->status()]);
        $this->assertContains(422, [$responses['nested']->status(), $responses['outer']->status()]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'Confirmed',
        ]);
        $this->assertSame(1, DB::table('order_status_history')
            ->where('order_id', $orderId)
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
}
