<?php

namespace Tests\Feature\Concurrency;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concurrency\Support\SqliteHttpRaceCase;
use Tests\TestCase;

/**
 * Order concurrency coverage extracted from OrderTest so race scenarios live
 * with the rest of the Concurrency category.
 *
 * These use a file-backed SQLite HTTP race: two independent PHP workers are
 * released together through a barrier file. SQLite serializes the eventual
 * write, so the assertions verify the retry/read-back result rather than
 * claiming simultaneous row locks (that is PostgreSQL's job — see the pgsql
 * harnesses).
 */
class OrderConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    use SqliteHttpRaceCase;

    protected function tearDown(): void
    {
        $this->stopRaceInfrastructure();
        parent::tearDown();
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
        $this->prepareRaceDatabase('order-race');
        [$outletUser, $outlet] = $this->createRaceOutlet();
        $product = $this->createRaceProduct([
            'price' => 25000,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);
        $this->startRaceServers('order', 'order-barrier');
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
     * Confirmed, and idempotently reuses the invoice. Both callers receive a
     * 200 and exactly one Confirmed history row is appended.
     */
    public function test_concurrent_admin_approvals_append_one_confirmed_history(): void
    {
        $this->prepareRaceDatabase('order-race');
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
        $this->startRaceServers('approval', 'order-barrier');
        $token = $this->raceLogin($admin->email);

        $responses = $this->runConcurrentHttpRequests([
            ['token' => $token, 'body' => [], 'method' => 'PUT'],
            ['token' => $token, 'body' => [], 'method' => 'PUT'],
        ], "/api/orders/{$order->id}/approve");
        $statuses = array_column($responses, 'status');
        sort($statuses);

        $this->assertSame([200, 200], $statuses);
        $this->assertSame('Confirmed', Order::on('race')->findOrFail($order->id)->status);
        $this->assertSame(1, OrderStatusHistory::on('race')
            ->where('order_id', $order->id)
            ->where('status', 'Confirmed')
            ->count());
    }
}
