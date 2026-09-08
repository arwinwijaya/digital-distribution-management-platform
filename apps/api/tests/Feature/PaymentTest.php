<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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

    protected function deliveredOrder(float $total = 100000): Order
    {
        $product = Product::factory()->create([
            'price' => $total,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $response = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'payment-order-'.$product->id,
        ]);
        $response->assertStatus(201);

        $order = Order::findOrFail($response->json('data.id'));
        $order->update(['status' => 'Delivered']);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'Delivered',
            'notes' => 'Delivered for payment test',
        ]);

        return $order->fresh();
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
        $order = $this->deliveredOrder(100000);

        $response = $this->withHeaders($this->adminHeaders())->postJson('/api/payments', [
            'order_id' => $order->id,
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
}
