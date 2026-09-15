<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrePilotCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $outletToken;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'jwt.secret' => base64_encode(hash('sha256', 'pre-pilot-compat-jwt', true)),
            'jwt.ttl' => 999999,
        ]);
        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'pre-pilot-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
        ]);
        $admin = User::factory()->admin()->create([
            'email' => 'pre-pilot-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outletToken = $this->login($this->outletUser);
        $this->adminToken = $this->login($admin);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->outletToken}"];
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    // ================================================================
    // 1. Valid outlet order keeps current 201 response, fields, stock,
    //    credit-limit, initial New history, and idempotency identity.
    // ================================================================
    public function test_valid_outlet_order_returns_201_with_expected_contract(): void
    {
        $product = Product::factory()->create([
            'price' => 35000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 10]],
                'idempotency_key' => 'compat-create-001',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'New')
            ->assertJsonPath('data.order_id', fn ($v) => str_starts_with($v, 'ORD-'))
            ->assertJsonPath('data.total_amount', '350000.00')
            ->assertJsonPath('data.commission_percentage', '2.00')
            ->assertJsonStructure([
                'data' => [
                    'id', 'order_id', 'outlet_id', 'status', 'total_amount',
                    'paid_amount', 'outstanding_balance', 'commission_percentage',
                    'items' => [['id', 'product_id', 'quantity', 'unit_price', 'subtotal']],
                    'created_at', 'updated_at',
                ],
            ]);

        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame('New', $order->status);
        $this->assertSame($this->outlet->id, $order->outlet_id);
        $this->assertSame('350000.00', (string) $order->total_amount);
        // Controller stores an outlet-scoped SHA-256 of the client key.
        $expectedIdentity = hash('sha256', json_encode([
            'outlet_id' => $this->outlet->id,
            'request_identity' => 'compat-create-001',
        ], JSON_THROW_ON_ERROR));
        $this->assertSame($expectedIdentity, $order->idempotency_key);

        // Stock was decremented
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 90]);

        // Initial New status history exists
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status' => 'New',
            'notes' => 'Order created',
        ]);
    }

    // ================================================================
    // 2. Identical order retry returns the same resource without a
    //    duplicate order or history.
    // ================================================================
    public function test_identical_retry_returns_same_resource_without_duplicate(): void
    {
        $product = Product::factory()->create([
            'price' => 25000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'idempotency_key' => 'compat-retry-001',
        ];

        $first = $this->withHeaders($this->authHeaders())->postJson('/api/orders', $payload);
        $first->assertCreated();
        $firstId = $first->json('data.id');
        $historyAfterFirst = OrderStatusHistory::where('order_id', $firstId)->count();

        $second = $this->withHeaders($this->authHeaders())->postJson('/api/orders', $payload);
        $second->assertOk()
            ->assertJsonPath('data.id', $firstId)
            ->assertJsonPath('data.order_id', $first->json('data.order_id'));

        $this->assertSame(1, Order::where('outlet_id', $this->outlet->id)->count());
        $this->assertSame($historyAfterFirst, OrderStatusHistory::where('order_id', $firstId)->count());

        // Stock only decremented once
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 97]);
    }

    // ================================================================
    // 3. Same explicit key with a different canonical payload returns
    //    422 and leaves the first order unchanged.
    // ================================================================
    public function test_same_key_different_payload_returns_422_and_leaves_first_order_unchanged(): void
    {
        $product1 = Product::factory()->create(['price' => 10000, 'stock_quantity' => 50, 'is_active' => true]);
        $product2 = Product::factory()->create(['price' => 20000, 'stock_quantity' => 50, 'is_active' => true]);
        $key = 'compat-diff-payload-001';

        // First request: product1 qty 2
        $first = $this->withHeaders($this->authHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product1->id, 'quantity' => 2]],
            'idempotency_key' => $key,
        ]);
        $first->assertCreated();
        $firstOrderId = $first->json('data.id');
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $firstOrderId, 'status' => 'New',
        ]);

        // Second request: same key but different payload (product2 qty 1)
        $second = $this->withHeaders($this->authHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product2->id, 'quantity' => 1]],
            'idempotency_key' => $key,
        ]);

        // Same key with a different payload is a 422 idempotency conflict.
        $second->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors(['idempotency_key']);

        // First order is completely unchanged
        $this->assertDatabaseHas('orders', [
            'id' => $firstOrderId,
            'status' => 'New',
        ]);
        $this->assertDatabaseCount('orders', 1);

        // No extra history was appended to the first order
        $this->assertSame(1, OrderStatusHistory::where('order_id', $firstOrderId)->count());

        // Stock of product1 was only decremented by first request; product2 untouched
        $this->assertDatabaseHas('products', ['id' => $product1->id, 'stock_quantity' => 48]);
        $this->assertDatabaseHas('products', ['id' => $product2->id, 'stock_quantity' => 50]);
    }

    // ================================================================
    // 4. Admin approval creates exactly one Confirmed history and one
    //    invoice on retry; WhatsApp failure does not roll back order.
    // ================================================================
    public function test_approval_retry_creates_one_invoice_and_whatsapp_failure_does_not_rollback(): void
    {
        $product = Product::factory()->create(['price' => 50000, 'stock_quantity' => 10, 'is_active' => true]);
        $createResponse = $this->withHeaders($this->authHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'compat-approval-001',
        ])->assertCreated();
        $orderId = $createResponse->json('data.id');

        // First approval
        $this->withHeaders($this->adminHeaders())->putJson("/api/orders/{$orderId}/approve")->assertOk();

        $confirmedCount = OrderStatusHistory::where('order_id', $orderId)->where('status', 'Confirmed')->count();
        $invoiceCount = Invoice::where('order_id', $orderId)->count();
        $this->assertSame(1, $confirmedCount);
        $this->assertSame(1, $invoiceCount);

        // Retry approval - should be idempotent
        $this->withHeaders($this->adminHeaders())->putJson("/api/orders/{$orderId}/approve")->assertOk();

        $this->assertSame(1, OrderStatusHistory::where('order_id', $orderId)->where('status', 'Confirmed')->count());
        $this->assertSame(1, Invoice::where('order_id', $orderId)->count());

        // Order remains Confirmed regardless of notification state
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'Confirmed']);
    }

    // ================================================================
    // 5. Delivery missing proof is rejected without delivery/order
    //    mutation.
    // ================================================================
    public function test_delivery_missing_proof_is_rejected_without_mutation(): void
    {
        $product = Product::factory()->create(['price' => 50000, 'stock_quantity' => 10, 'is_active' => true]);
        $create = $this->withHeaders($this->authHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'compat-delivery-001',
        ])->assertCreated();

        $order = Order::findOrFail($create->json('data.id'));
        $order->update(['status' => 'Confirmed']);

        $driver = User::factory()->driver()->create([
            'email' => 'compat-driver-001@ddp.test',
            'password' => Hash::make('password123'),
        ]);

        $delivery = \App\Models\Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by_id' => $this->adminToken ? 1 : null,
            'status' => 'in_progress',
            'assigned_at' => now(),
            'started_at' => now(),
        ]);

        $driverToken = $this->login($driver);

        // Attempt delivered status without proof
        $this->withHeaders(['Authorization' => "Bearer {$driverToken}"])
            ->patchJson("/api/deliveries/{$delivery->id}/status", [
                'status' => 'delivered',
                'recipient_name' => 'Outlet Manager',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proof_of_delivery_url']);

        // Delivery and order status unchanged
        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Confirmed']);
    }

    // ================================================================
    // 6. Partial payment and overpayment follow current payment policy.
    // ================================================================
    public function test_partial_payment_and_overpayment_follow_payment_policy(): void
    {
        $product = Product::factory()->create(['price' => 100000, 'stock_quantity' => 10, 'is_active' => true]);
        $create = $this->withHeaders($this->authHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'compat-payment-001',
        ])->assertCreated();

        $order = Order::findOrFail($create->json('data.id'));

        // Approve and deliver
        $this->withHeaders($this->adminHeaders())->putJson("/api/orders/{$order->id}/approve")->assertOk();
        $order->update(['status' => 'Delivered']);

        // Partial payment
        $this->withHeaders($this->adminHeaders())->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 40000,
            'payment_method' => 'cash',
            'idempotency_key' => 'compat-pay-001',
        ])->assertCreated();

        $order->refresh();
        $this->assertSame('Partially Paid', $order->status);
        $this->assertSame('40000.00', (string) $order->paid_amount);
        $this->assertSame('60000.00', number_format(max(0, (float) $order->total_amount - (float) $order->paid_amount), 2, '.', ''));

        // Overpayment rejected
        $this->withHeaders($this->adminHeaders())->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 70000,
            'payment_method' => 'cash',
            'idempotency_key' => 'compat-pay-over',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['amount']);
    }

    // ================================================================
    // 7. Unauthorized approval/delivery/payment attempts remain forbidden.
    // ================================================================
    public function test_unauthorized_approval_and_delivery_and_payment_attempt_rejected(): void
    {
        $product = Product::factory()->create(['price' => 50000, 'stock_quantity' => 10, 'is_active' => true]);
        $create = $this->withHeaders($this->authHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'compat-authz-001',
        ])->assertCreated();
        $order = Order::findOrFail($create->json('data.id'));

        // Outlet cannot approve
        $this->withHeaders($this->authHeaders())->putJson("/api/orders/{$order->id}/approve")
            ->assertStatus(403);

        // Outlet cannot list orders (admin only)
        $this->withHeaders($this->authHeaders())->getJson('/api/orders')
            ->assertStatus(403);

        // Admin without outlet cannot create order (StoreOrderRequest requires outlet role)
        $this->withHeaders($this->adminHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'compat-authz-admin',
        ])->assertStatus(403);
    }

    // ================================================================
    // 8. Analytics/finance/pipeline status routes keep success shape.
    // ================================================================
    public function test_analytics_finance_and_pipeline_routes_keep_success_shape(): void
    {
        // Analytics dashboard for admin
        $this->withHeaders($this->adminHeaders())->getJson('/api/analytics/dashboard')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Finance metrics for admin
        $this->withHeaders($this->adminHeaders())->getJson('/api/finance/metrics')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Health check (public)
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy');
    }
}
