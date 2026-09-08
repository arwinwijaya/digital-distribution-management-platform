<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $outletToken;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'payment-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
        ]);
        $this->outletToken = $this->postJson('/api/auth/login', [
            'email' => 'payment-outlet@ddp.test',
            'password' => 'password123',
        ])->json('data.token');

        $admin = User::factory()->admin()->create([
            'email' => 'payment-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');
    }

    protected function outletHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->outletToken}"];
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    protected function createOrder(float $total, string $identity): Order
    {
        $product = Product::factory()->create([
            'price' => $total,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $response = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => $identity,
        ]);
        $response->assertStatus(201);

        return Order::findOrFail($response->json('data.id'));
    }

    protected function deliveredOrder(float $total = 100000): Order
    {
        $order = $this->createOrder($total, 'payment-order-'.uniqid());
        $order->update(['status' => 'Delivered']);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'Delivered',
            'notes' => 'Delivered for payment test',
        ]);

        return $order->fresh();
    }

    protected function recordPayment(Order $order, float $amount, string $identity): TestResponse
    {
        return $this->withHeaders($this->adminHeaders())->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'idempotency_key' => $identity,
        ]);
    }

    protected function setCreditLimit(float $amount): void
    {
        if (!Schema::hasTable('credit_limits')) {
            Schema::create('credit_limits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('outlet_id')->unique();
                $table->decimal('limit_amount', 14, 2);
                $table->timestamps();
            });
        }

        DB::table('credit_limits')->updateOrInsert(
            ['outlet_id' => $this->outlet->id],
            ['limit_amount' => $amount, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /**
     * RED CYCLE 1: Given order is delivered, when recording payment, then the
     * payment is recorded and the order balance is updated.
     */
    public function test_admin_can_record_payment_for_delivered_order(): void
    {
        $this->mock(ReceiptService::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn('RCT-MOCKED');
        });
        $order = $this->deliveredOrder(100000);

        $response = $this->withHeaders($this->adminHeaders())->postJson('/api/payments', [
            'order_id' => $order->order_id,
            'amount' => 40000,
            'payment_method' => 'cash',
            'idempotency_key' => 'payment-'.$order->id.'-1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.amount', '40000.00')
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.order.outstanding_balance', '60000.00');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 40000,
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'Partially Paid',
        ]);
    }

    /**
     * RED CYCLE 2: Given an outlet has pending orders exceeding its credit,
     * when placing another order, the submission is rejected before mutation.
     */
    public function test_order_is_blocked_when_credit_limit_would_be_exceeded(): void
    {
        $this->setCreditLimit(50000);
        $existingProduct = Product::factory()->create([
            'price' => 40000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        $newProduct = Product::factory()->create([
            'price' => 20000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $existing = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $existingProduct->id, 'quantity' => 1]],
            'idempotency_key' => 'credit-existing',
        ]);
        $existing->assertStatus(201);
        $beforeCount = Order::count();
        $beforeStock = $newProduct->fresh()->stock_quantity;

        $response = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $newProduct->id, 'quantity' => 1]],
            'idempotency_key' => 'credit-over-limit',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
        $this->assertSame($beforeCount, Order::count());
        $this->assertSame($beforeStock, $newProduct->fresh()->stock_quantity);
    }

    public function test_partial_payments_have_non_negative_balance_and_replay_is_idempotent(): void
    {
        $order = $this->deliveredOrder(100000);

        $this->recordPayment($order, 30000, 'partial-1')->assertStatus(201);
        $replay = $this->recordPayment($order, 30000, 'partial-1');
        $replay->assertStatus(200)->assertJsonPath('data.order.outstanding_balance', '70000.00');
        $this->recordPayment($order, 70001, 'partial-overpayment')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
        $this->recordPayment($order, 70000, 'partial-2')
            ->assertStatus(201)
            ->assertJsonPath('data.order.outstanding_balance', '0.00');
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Paid', 'paid_amount' => 100000]);
    }

    public function test_outstanding_balance_includes_pending_and_unpaid_orders_but_excludes_paid_orders(): void
    {
        $new = $this->createOrder(10000, 'outstanding-new');
        $confirmed = $this->createOrder(20000, 'outstanding-confirmed');
        $confirmed->update(['status' => 'Confirmed']);
        $delivered = $this->deliveredOrder(30000);
        $paid = $this->deliveredOrder(40000);
        $this->recordPayment($paid, 40000, 'outstanding-paid')->assertStatus(201);

        $this->setCreditLimit(200000);
        $response = $this->withHeaders($this->outletHeaders())->getJson('/api/credit-limit');
        $response->assertStatus(200)
            ->assertJsonPath('data.credit_limit', '200000.00')
            ->assertJsonPath('data.outstanding_balance', '60000.00')
            ->assertJsonPath('data.available_credit', '140000.00');

        $this->assertSame('New', $new->fresh()->status);
        $this->assertSame('Confirmed', $confirmed->fresh()->status);
        $this->assertSame('Delivered', $delivered->fresh()->status);
        $this->assertSame('Paid', $paid->fresh()->status);
    }

    public function test_zero_credit_limit_rejects_order_before_order_or_stock_mutation(): void
    {
        $this->setCreditLimit(0);
        $product = Product::factory()->create(['price' => 1, 'stock_quantity' => 4, 'is_active' => true]);
        $before = Order::count();

        $response = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'zero-limit',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['credit_limit']);
        $this->assertSame($before, Order::count());
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_payment_rejects_unauthorized_order_and_invalid_status_or_amount(): void
    {
        $order = $this->deliveredOrder(100000);
        $otherUser = User::factory()->outlet()->create([
            'email' => 'other-payment-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        Outlet::factory()->create(['user_id' => $otherUser->id]);
        $otherToken = $this->postJson('/api/auth/login', [
            'email' => $otherUser->email,
            'password' => 'password123',
        ])->json('data.token');

        $this->withHeaders(['Authorization' => "Bearer {$otherToken}"])
            ->postJson('/api/payments', [
                'order_id' => $order->id,
                'amount' => 1,
                'payment_method' => 'cash',
                'idempotency_key' => 'unauthorized-payment',
            ])->assertStatus(403);

        $this->recordPayment($order, 1, 'replay-auth')->assertStatus(201);
        $this->withHeaders(['Authorization' => "Bearer {$otherToken}"])
            ->postJson('/api/payments', [
                'order_id' => $order->id,
                'amount' => 1,
                'payment_method' => 'cash',
                'idempotency_key' => 'replay-auth',
            ])->assertStatus(403);

        $this->recordPayment($order, 100001, 'over-payment')->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $newOrder = $this->createOrder(10000, 'invalid-payment-status');
        $this->recordPayment($newOrder, 1, 'invalid-status')->assertStatus(422)->assertJsonValidationErrors(['order_id']);
        $this->recordPayment($order, 0, 'zero-payment')->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }
}
