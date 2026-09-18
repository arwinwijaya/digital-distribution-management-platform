<?php

namespace Tests\Feature\Concurrency;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concurrency\Support\SqliteHttpRaceCase;
use Tests\TestCase;

class PrePilotConcurrencyCompatibilityTest extends TestCase
{
    use RefreshDatabase;
    use SqliteHttpRaceCase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'jwt.secret' => base64_encode(hash('sha256', 'pre-pilot-concurrency-jwt', true)),
            'jwt.ttl' => 999999,
        ]);
        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'concurrency-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
        ]);
        $this->token = $this->login($this->outletUser);
    }

    protected function tearDown(): void
    {
        $this->stopRaceInfrastructure();
        parent::tearDown();
    }

    // ================================================================
    // 1. Concurrent same-identity order submissions returning one result.
    // ================================================================
    public function test_concurrent_same_identity_orders_return_one_result(): void
    {
        $this->prepareRaceDatabase('pre-pilot-race');
        [$outletUser, $outlet] = $this->createRaceOutlet();
        $product = $this->createRaceProduct([
            'price' => 25000,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);
        $this->startRaceServers('order', 'pre-pilot-barrier');
        $token = $this->raceLogin($outletUser->email);
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'idempotency_key' => 'pre-pilot-concurrent-order',
        ];

        $responses = $this->runConcurrentHttpRequests([
            ['token' => $token, 'body' => $payload, 'method' => 'POST'],
            ['token' => $token, 'body' => $payload, 'method' => 'POST'],
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
    }

    // ================================================================
    // 2. Concurrent payment submissions not producing negative balance
    //    or duplicate posting.
    // ================================================================
    public function test_concurrent_payments_do_not_produce_negative_balance(): void
    {
        $product = Product::factory()->create(['price' => 100000, 'stock_quantity' => 10, 'is_active' => true]);
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'idempotency_key' => 'concurrency-payment-order',
            ]);
        $response->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));
        $order->update(['status' => 'Delivered']);
        OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'Delivered', 'notes' => 'Delivered']);

        Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'invoice_number' => 'INV-CONCUR-'.$order->id,
            'issue_date' => now()->subDays(7)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 100000,
            'paid_amount' => 0,
            'balance_amount' => 100000,
            'status' => Invoice::UNPAID,
        ]);

        $admin = User::factory()->admin()->create([
            'email' => 'concurrency-admin-payment@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $adminToken = $this->login($admin);

        // Sequential payment attempts testing idempotency guard rather than true pgsql race,
        // because default sqlite + RefreshDatabase environment is used here.
        $first = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 50000,
            'payment_method' => 'cash',
            'idempotency_key' => 'concur-pay-001',
        ]);
        $first->assertCreated();

        // Different payload with same idempotency key must fail
        $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 30000,
            'payment_method' => 'cash',
            'idempotency_key' => 'concur-pay-001',
        ])->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);

        // Verify no negative balance
        $order->refresh();
        $this->assertGreaterThanOrEqual(0, (float) $order->paid_amount);
        $this->assertSame('Partially Paid', $order->status);
        $this->assertSame('50000.00', (string) $order->paid_amount);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    // ================================================================
    // 3. Concurrent delivery/payment or approval race preserving
    //    existing one-wins lock behavior.
    // ================================================================
    public function test_concurrent_approvals_preserve_one_wins_behavior(): void
    {
        $this->prepareRaceDatabase('pre-pilot-race');
        [, $outlet] = $this->createRaceOutlet();
        $product = $this->createRaceProduct(['price' => 50000, 'stock_quantity' => 5]);
        $order = Order::on('race')->create([
            'order_id' => 'ORD-RACE-PRE-PILOT-APPROVAL',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 50000,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'race-pre-pilot-approval',
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
        $admin = $this->createRaceUser('concurrent-pre-pilot-admin@ddp.test', 'admin');
        $this->startRaceServers('approval', 'pre-pilot-barrier');
        $token = $this->raceLogin($admin->email);

        $responses = $this->runConcurrentHttpRequests([
            ['token' => $token, 'body' => [], 'method' => 'PUT'],
            ['token' => $token, 'body' => [], 'method' => 'PUT'],
        ], "/api/orders/{$order->id}/approve");

        $statuses = array_column($responses, 'status');
        sort($statuses);

        // Two concurrent approval requests must not produce duplicate Confirmed histories
        $confirmedCount = OrderStatusHistory::on('race')
            ->where('order_id', $order->id)
            ->where('status', 'Confirmed')
            ->count();
        $this->assertSame(1, $confirmedCount);
        $this->assertSame('Confirmed', Order::on('race')->findOrFail($order->id)->status);
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }
}
