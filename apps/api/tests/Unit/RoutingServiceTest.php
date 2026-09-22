<?php

namespace Tests\Unit;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Services\RoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoutingService $routing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routing = new RoutingService();
    }

    public function test_plan_orders_stops_nearest_neighbor_from_first_stop(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $a = $this->makeDelivery($driver, -6.2000, 106.8000); // origin (lowest id)
        $b = $this->makeDelivery($driver, -6.2010, 106.8000); // closest to A
        $c = $this->makeDelivery($driver, -6.3000, 106.9000); // farthest

        $plan = $this->routing->plan($a->fresh());

        $this->assertNotSame([], $plan);
        $this->assertArrayHasKey('stops', $plan);
        $this->assertArrayHasKey('total_distance_km', $plan);

        $ids = array_column($plan['stops'], 'id');
        $this->assertSame([$a->id, $b->id, $c->id], $ids);

        // First stop has no travel cost; the rest carry the leg distance.
        $this->assertSame(0.0, $plan['stops'][0]['distance_km']);
        $this->assertGreaterThan(0, $plan['stops'][1]['distance_km']);
        $this->assertGreaterThan(
            $plan['stops'][1]['distance_km'],
            $plan['stops'][2]['distance_km'],
        );
        $this->assertEqualsWithDelta(
            $plan['stops'][1]['distance_km'] + $plan['stops'][2]['distance_km'],
            $plan['total_distance_km'],
            0.0001,
        );
    }

    public function test_plan_is_deterministic(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $this->makeDelivery($driver, -6.2000, 106.8000);
        $this->makeDelivery($driver, -6.2100, 106.8100);
        $this->makeDelivery($driver, -6.1900, 106.7900);

        $first = $this->routing->plan(Delivery::where('driver_id', $driver->id)->orderBy('id')->first());
        $second = $this->routing->plan(Delivery::where('driver_id', $driver->id)->orderBy('id')->first());

        $this->assertSame($first, $second);
    }

    public function test_plan_caps_stops_at_max_stops(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        for ($i = 0; $i < RoutingService::MAX_STOPS + 5; $i++) {
            $this->makeDelivery($driver, -6.2000 - ($i * 0.001), 106.8000 + ($i * 0.001));
        }

        $plan = $this->routing->plan(Delivery::where('driver_id', $driver->id)->orderBy('id')->first());

        $this->assertCount(RoutingService::MAX_STOPS, $plan['stops']);
    }

    public function test_plan_skips_stops_without_coordinates(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $withCoords = $this->makeDelivery($driver, -6.2000, 106.8000);
        $noCoords = $this->makeDelivery($driver, null, null);

        $plan = $this->routing->plan($withCoords->fresh());

        $ids = array_column($plan['stops'], 'id');
        $this->assertContains($withCoords->id, $ids);
        $this->assertNotContains($noCoords->id, $ids);
    }

    public function test_plan_returns_empty_array_when_no_stop_has_coordinates(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $delivery = $this->makeDelivery($driver, null, null);

        $this->assertSame([], $this->routing->plan($delivery->fresh()));
    }

    private function makeDelivery(User $driver, ?float $latitude, ?float $longitude): Delivery
    {
        $outlet = Outlet::factory()->create([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        $order = Order::create([
            'order_id' => 'ORD-ROUTE-'.uniqid('', true),
            'outlet_id' => $outlet->id,
            'status' => 'Confirmed',
            'total_amount' => 100000,
            'idempotency_key' => 'route-'.uniqid('', true),
        ]);

        return Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by_id' => $driver->id,
            'status' => Delivery::ASSIGNED,
        ]);
    }
}
