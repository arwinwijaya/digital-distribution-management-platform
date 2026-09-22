<?php

namespace Tests\Feature\FieldOps;

use App\Models\Delivery;
use App\Models\DeliveryLocationPing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DeliveryTestFixtures;
use Tests\TestCase;

/**
 * Phase 8 — T7: delivery location ping (driver) + admin live-track read.
 *
 * AC-4 contract: only the assigned driver may ping their delivery, coordinates
 * are validated, and the admin track payload returns the latest position plus a
 * bounded, newest-first ping list.
 */
class DeliveryTrackingTest extends TestCase
{
    use DeliveryTestFixtures, RefreshDatabase;

    public function test_assigned_driver_can_ping_their_delivery(): void
    {
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-1', 'trk-1');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->postJson("/api/deliveries/{$delivery->id}/location", [
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
                'accuracy_m' => 8,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.latitude', '-6.2000000')
            ->assertJsonPath('data.accuracy_m', 8);

        $this->assertDatabaseHas('delivery_location_pings', [
            'delivery_id' => $delivery->id,
            'accuracy_m' => 8,
        ]);
        $this->assertNotNull(DeliveryLocationPing::first()->recorded_at);
    }

    public function test_another_driver_cannot_ping_the_delivery(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-2', 'trk-2');
        $intruder = User::factory()->driver()->create([
            'email' => 'intruder-trk@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($intruder))
            ->postJson("/api/deliveries/{$delivery->id}/location", [
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
            ])
            ->assertStatus(403);
    }

    public function test_ping_rejects_invalid_coordinates(): void
    {
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-3', 'trk-3');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->postJson("/api/deliveries/{$delivery->id}/location", [
                'latitude' => 120,
                'longitude' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_ping_requires_authentication(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-4', 'trk-4');

        $this->postJson("/api/deliveries/{$delivery->id}/location", [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ])->assertStatus(401);
    }

    public function test_admin_track_returns_last_position_and_bounded_pings(): void
    {
        ['admin' => $admin, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-5', 'trk-5');

        foreach (range(1, 5) as $i) {
            $delivery->locationPings()->create([
                'latitude' => -6.2000000 - ($i / 10000),
                'longitude' => 106.8166667,
                'recorded_at' => now()->addMinutes($i),
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
            ->getJson("/api/admin/deliveries/{$delivery->id}/track");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.delivery_id', $delivery->id)
            ->assertJsonCount(5, 'data.pings')
            ->assertJsonPath('data.last_position.latitude', '-6.2005000');
    }

    public function test_track_pings_are_capped(): void
    {
        ['admin' => $admin, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-6', 'trk-6');

        foreach (range(1, 60) as $i) {
            $delivery->locationPings()->create([
                'latitude' => -6.2,
                'longitude' => 106.8,
                'recorded_at' => now()->addSeconds($i),
            ]);
        }

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
            ->getJson("/api/admin/deliveries/{$delivery->id}/track")
            ->assertOk()
            ->assertJsonCount(50, 'data.pings');
    }

    public function test_track_returns_null_last_position_when_no_pings(): void
    {
        ['admin' => $admin, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-7', 'trk-7');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
            ->getJson("/api/admin/deliveries/{$delivery->id}/track")
            ->assertOk()
            ->assertJsonPath('data.last_position', null)
            ->assertJsonCount(0, 'data.pings');
    }

    public function test_sales_user_cannot_read_admin_track(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-8', 'trk-8');
        $sales = User::factory()->sales()->create([
            'email' => 'sales-track@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($sales))
            ->getJson("/api/admin/deliveries/{$delivery->id}/track")
            ->assertStatus(403);
    }

    public function test_track_requires_authentication(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-9', 'trk-9');

        $this->getJson("/api/admin/deliveries/{$delivery->id}/track")->assertStatus(401);
    }

    public function test_ping_on_unknown_delivery_is_not_found(): void
    {
        ['driver' => $driver] = $this->createDeliveryFixture('ORD-TRK-10', 'trk-10');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($driver))
            ->postJson('/api/deliveries/999999/location', [
                'latitude' => -6.2,
                'longitude' => 106.8,
            ])
            ->assertStatus(404);
    }

    public function test_ping_delivery_is_not_found_for_delivery_of_another_driver_via_missing_row(): void
    {
        // Regression: a driver must not be able to probe other deliveries.
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-11', 'trk-11');
        $other = User::factory()->driver()->create([
            'email' => 'other-trk@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($other))
            ->postJson("/api/deliveries/{$delivery->id}/location", [
                'latitude' => -6.2,
                'longitude' => 106.8,
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('delivery_location_pings', 0);
    }

    public function test_delivery_model_exposes_location_pings_relation(): void
    {
        ['delivery' => $delivery] = $this->createDeliveryFixture('ORD-TRK-12', 'trk-12');

        $this->assertInstanceOf(Delivery::class, $delivery);
        $this->assertCount(0, $delivery->locationPings);
    }
}
