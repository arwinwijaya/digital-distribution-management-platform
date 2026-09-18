<?php

namespace Tests\Feature\Concurrency;

use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concurrency\Support\SqliteHttpRaceCase;
use Tests\TestCase;

class DeliveryConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    use SqliteHttpRaceCase;

    protected function tearDown(): void
    {
        $this->stopRaceInfrastructure();
        parent::tearDown();
    }

    /**
     * Completion and payment use independent public HTTP workers. The shared
     * barrier releases both transactions before they contend for the order
     * row; assertions verify the lock preserves matching status/history data.
     */
    public function test_concurrent_delivery_completion_and_payment_keep_order_history_consistent(): void
    {
        $scenario = $this->createRaceScenario();
        $race = $this->runRaceWorkers($scenario);

        $this->assertRaceOutcome($scenario['order'], $race['responses']);
        $this->assertTrackingOutcome($scenario['order'], $race['outlet_token']);
    }

    /** @return array{order: Order, delivery: Delivery, driver: User, outlet: User} */
    private function createRaceScenario(): array
    {
        $this->prepareRaceDatabase('delivery-race');
        $outletUser = $this->createRaceUser('delivery-race-outlet@example.com', 'outlet', 'password');
        $outlet = Outlet::factory()->make(['user_id' => $outletUser->id, 'is_active' => true]);
        $outlet->setConnection('race');
        $outlet->save();
        $driver = $this->createRaceUser('delivery-race-driver@example.com', 'driver', 'password');
        $admin = $this->createRaceUser('delivery-race-admin@example.com', 'admin', 'password');
        $order = Order::on('race')->create(['order_id' => 'ORD-RACE-DELIVERY', 'outlet_id' => $outlet->id, 'status' => 'Confirmed', 'total_amount' => 100000, 'idempotency_key' => 'delivery-race-order']);
        OrderStatusHistory::on('race')->create(['order_id' => $order->id, 'status' => 'Confirmed', 'notes' => 'Order approved']);
        $delivery = Delivery::on('race')->create(['order_id' => $order->id, 'driver_id' => $driver->id, 'assigned_by_id' => $admin->id, 'status' => Delivery::IN_PROGRESS, 'assigned_at' => now()->subMinute(), 'started_at' => now()]);
        DeliveryStatusHistory::on('race')->insert([
            ['delivery_id' => $delivery->id, 'actor_id' => $admin->id, 'from_status' => null, 'status' => Delivery::ASSIGNED, 'metadata' => null, 'notes' => null, 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()],
            ['delivery_id' => $delivery->id, 'actor_id' => $driver->id, 'from_status' => Delivery::ASSIGNED, 'status' => Delivery::IN_PROGRESS, 'metadata' => null, 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return ['order' => $order, 'delivery' => $delivery, 'driver' => $driver, 'outlet' => $outletUser];
    }

    /** @return array{responses: array, outlet_token: string} */
    private function runRaceWorkers(array $scenario): array
    {
        $this->startRaceServers('payment', 'delivery-barrier');
        $driverToken = $this->raceLogin($scenario['driver']->email, 'password');
        $outletToken = $this->raceLogin($scenario['outlet']->email, 'password');
        $responses = $this->runConcurrentHttpRequests([
            ['token' => $driverToken, 'path' => "/api/deliveries/{$scenario['delivery']->id}/status", 'method' => 'PATCH', 'body' => ['status' => 'delivered', 'recipient_name' => 'Outlet manager', 'proof_of_delivery_url' => 'https://example.com/race-proof.jpg']],
            ['token' => $outletToken, 'path' => '/api/payments', 'method' => 'POST', 'body' => ['order_id' => $scenario['order']->id, 'amount' => 100000, 'payment_method' => 'cash', 'idempotency_key' => 'delivery-race-payment']],
        ]);

        return ['responses' => $responses, 'outlet_token' => $outletToken];
    }

    private function assertRaceOutcome(Order $order, array $responses): void
    {
        $this->assertSame(200, $responses[0]['status'], json_encode($responses[0]));
        $this->assertContains($responses[1]['status'], [200, 422], json_encode($responses[1]));
        $finalOrder = Order::on('race')->findOrFail($order->id);
        $history = OrderStatusHistory::on('race')->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame($finalOrder->status, $history->last()->status);
        $this->assertSame(1, $history->where('status', 'Delivered')->count());
        if ($responses[1]['status'] === 422) {
            $this->assertSame('Delivered', $finalOrder->status);
        } else {
            $this->assertSame('Paid', $finalOrder->status);
            $this->assertSame(1, $history->where('status', 'Paid')->count());
        }
    }

    private function assertTrackingOutcome(Order $order, string $outletToken): void
    {
        $tracking = $this->publicHttpRequest('GET', $this->raceUrl(0)."/api/orders/{$order->id}", [], $outletToken);
        $this->assertSame(200, $tracking['status'], json_encode($tracking));
        $this->assertSame(Order::on('race')->findOrFail($order->id)->status, $tracking['json']['data']['status']);
        $this->assertSame(1, count(array_filter($tracking['json']['data']['status_history'], fn (array $entry): bool => $entry['status'] === 'Delivered')));
    }
}
