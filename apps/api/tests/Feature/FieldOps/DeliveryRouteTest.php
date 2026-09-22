<?php

namespace Tests\Feature\FieldOps;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DeliveryTestFixtures;
use Tests\TestCase;

/**
 * Phase 8 — T9: routing plan persistence + admin route read endpoint.
 *
 * AC-3 contract: assigning a delivery to a driver snapshots the bounded
 * nearest-neighbor plan into `route_data` when the outlet carries coordinates,
 * and leaves it null (without error) otherwise. Admins can read the plan back.
 */
class DeliveryRouteTest extends TestCase
{
    use DeliveryTestFixtures, RefreshDatabase;

    private function confirmedOrder(Outlet $outlet, string $reference, string $key): Order
    {
        return Order::create([
            'order_id' => $reference,
            'outlet_id' => $outlet->id,
            'status' => 'Confirmed',
            'total_amount' => 100000,
            'idempotency_key' => $key,
        ]);
    }

    public function test_store_persists_route_data_when_outlet_has_coordinates(): void
    {
        // Fixture delivery is created directly (no route snapshot); a second order
        // for the same driver exercises the multi-stop plan on a fresh assignment.
        ['admin' => $admin, 'driver' => $driver, 'delivery' => $first] = $this->createDeliveryFixture('ORD-RTE-1', 'rte-1');

        $secondOutlet = Outlet::factory()->create([
            'latitude' => -6.2500000,
            'longitude' => 106.8500000,
        ]);
        $second = $this->confirmedOrder($secondOutlet, 'ORD-RTE-B', 'rte-b');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
            ->postJson('/api/deliveries', ['order_id' => $second->id, 'driver_id' => $driver->id])
            ->assertCreated()
            ->assertJsonPath('data.route_data.stops.0.id', fn ($id) => is_int($id));

        $latest = Delivery::where('order_id', $second->id)->firstOrFail();
        $this->assertIsArray($latest->route_data);
        // Fixture outlet + new outlet both carry coordinates -> 2 stops.
        $this->assertCount(2, $latest->route_data['stops']);
        $this->assertGreaterThan(0, $latest->route_data['total_distance_km']);
        $this->assertSame(
            [$first->id, $latest->id],
            array_column($latest->route_data['stops'], 'id'),
        );
    }

    public function test_store_leaves_route_data_null_when_outlet_has_no_coordinates(): void
    {
        // The only other delivery for this driver is already closed, so the new
        // (coordinate-less) assignment is the sole active candidate -> empty plan.
        ['admin' => $admin, 'driver' => $driver] = $this->createDeliveryFixture(
            'ORD-RTE-2',
            'rte-2',
            Delivery::DELIVERED,
        );
        $outlet = Outlet::factory()->create(['latitude' => null, 'longitude' => null]);
        $order = $this->confirmedOrder($outlet, 'ORD-RTE-C', 'rte-c');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
            ->postJson('/api/deliveries', ['order_id' => $order->id, 'driver_id' => $driver->id])
            ->assertCreated()
            ->assertJsonPath('data.route_data', null);

        $this->assertNull(Delivery::where('order_id', $order->id)->value('route_data'));
    }

    public function test_admin_can_read_the_scheduled_route(): void
    {
        ['admin' => $admin, 'driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-RTE-3', 'rte-3');
        $delivery->update([
            'route_data' => [
                'stops' => [['id' => $delivery->id, 'latitude' => -6.2, 'longitude' => 106.8, 'distance_km' => 0.0]],
                'total_distance_km' => 0.0,
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
            ->getJson("/api/admin/deliveries/{$delivery->id}/route")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.delivery_id', $delivery->id)
            ->assertJsonPath('data.route_data.stops.0.id', $delivery->id)
            ->assertJsonPath('data.route_data.total_distance_km', 0);
    }

    public function test_route_read_returns_null_when_no_plan(): void
    {
        ['admin' => $admin, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-RTE-4', 'rte-4');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
            ->getJson("/api/admin/deliveries/{$delivery->id}/route")
            ->assertOk()
            ->assertJsonPath('data.route_data', null);
    }

    public function test_route_read_is_forbidden_for_sales(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-RTE-5', 'rte-5');
        $sales = User::factory()->sales()->create([
            'email' => 'sales-route@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($sales))
            ->getJson("/api/admin/deliveries/{$delivery->id}/route")
            ->assertStatus(403);
    }

    public function test_route_read_requires_authentication(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-RTE-6', 'rte-6');

        $this->getJson("/api/admin/deliveries/{$delivery->id}/route")->assertStatus(401);
    }

    public function test_route_read_on_unknown_delivery_is_not_found(): void
    {
        ['admin' => $admin] = $this->createDeliveryFixture('ORD-RTE-7', 'rte-7');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
            ->getJson('/api/admin/deliveries/999999/route')
            ->assertStatus(404);
    }
}
