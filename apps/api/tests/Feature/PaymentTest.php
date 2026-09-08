<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
