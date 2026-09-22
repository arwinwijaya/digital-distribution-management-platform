<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\SalesVisit;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FieldOpsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exposes_field_ops_tables_and_columns(): void
    {
        $this->assertTrue(Schema::hasTable('driver_profiles'));
        $this->assertTrue(Schema::hasTable('delivery_location_pings'));

        $this->assertTrue(Schema::hasColumns('driver_profiles', [
            'user_id',
            'vehicle_type',
            'plate_number',
            'capacity_kg',
            'service_territory_id',
            'shift_start',
            'shift_end',
            'is_available',
        ]));

        $this->assertTrue(Schema::hasColumns('delivery_location_pings', [
            'delivery_id',
            'latitude',
            'longitude',
            'accuracy_m',
            'recorded_at',
        ]));

        $this->assertTrue(Schema::hasColumns('sales_visits', [
            'check_in_at',
            'check_in_latitude',
            'check_in_longitude',
            'check_in_accuracy_m',
            'check_out_at',
            'check_out_latitude',
            'check_out_longitude',
        ]));

        $this->assertTrue(Schema::hasColumns('deliveries', [
            'pod_captured_at',
            'pod_latitude',
            'pod_longitude',
        ]));
    }

    public function test_driver_profile_is_unique_per_user_and_references_territory(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $territory = Territory::create(['name' => 'Jakarta Selatan', 'code' => 'JKS']);

        DB::table('driver_profiles')->insert([
            'user_id' => $driver->id,
            'vehicle_type' => 'motor',
            'plate_number' => 'B 1234 XYZ',
            'capacity_kg' => 50,
            'service_territory_id' => $territory->id,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $driver->id,
            'vehicle_type' => 'motor',
            'is_available' => true,
        ]);

        $this->expectException(QueryException::class);

        DB::table('driver_profiles')->insert([
            'user_id' => $driver->id,
            'vehicle_type' => 'motor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_delivery_location_ping_cascades_when_delivery_is_deleted(): void
    {
        $delivery = $this->makeDelivery();

        DB::table('delivery_location_pings')->insert([
            'delivery_id' => $delivery->id,
            'latitude' => -6.2607000,
            'longitude' => 106.7816000,
            'accuracy_m' => 12,
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('delivery_location_pings', 1);

        $delivery->delete();

        $this->assertDatabaseCount('delivery_location_pings', 0);
    }

    public function test_sales_visit_can_store_gps_checkin_and_checkout(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $outlet = Outlet::factory()->create();

        $visit = SalesVisit::create([
            'sales_user_id' => $sales->id,
            'outlet_id' => $outlet->id,
            'visit_date' => '2026-09-22',
            'status' => 'planned',
        ]);

        $visit->forceFill([
            'check_in_at' => now(),
            'check_in_latitude' => -6.2607000,
            'check_in_longitude' => 106.7816000,
            'check_in_accuracy_m' => 15,
            'check_out_at' => now()->addMinutes(30),
            'check_out_latitude' => -6.2610000,
            'check_out_longitude' => 106.7820000,
        ])->save();

        $fresh = $visit->fresh();

        $this->assertNotNull($fresh->check_in_at);
        $this->assertEqualsWithDelta(-6.2607, (float) $fresh->check_in_latitude, 0.0000001);
        $this->assertSame(15, (int) $fresh->check_in_accuracy_m);
        $this->assertNotNull($fresh->check_out_at);
    }

    public function test_delivery_can_store_pod_capture_metadata(): void
    {
        $delivery = $this->makeDelivery();

        $delivery->forceFill([
            'pod_captured_at' => now(),
            'pod_latitude' => -6.2607000,
            'pod_longitude' => 106.7816000,
        ])->save();

        $fresh = $delivery->fresh();

        $this->assertNotNull($fresh->pod_captured_at);
        $this->assertEqualsWithDelta(-6.2607, (float) $fresh->pod_latitude, 0.0000001);
        $this->assertEqualsWithDelta(106.7816, (float) $fresh->pod_longitude, 0.0000001);
    }

    private function makeDelivery(): Delivery
    {
        $outlet = Outlet::factory()->create();
        $sales = User::factory()->create(['role' => 'sales']);
        $driver = User::factory()->create(['role' => 'driver']);

        $order = Order::create([
            'order_id' => 'ORD-FIELDOPS-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Confirmed',
            'total_amount' => 100000,
            'idempotency_key' => 'fieldops-'.uniqid(),
        ]);

        return Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by_id' => $sales->id,
            'status' => Delivery::ASSIGNED,
        ]);
    }
}
