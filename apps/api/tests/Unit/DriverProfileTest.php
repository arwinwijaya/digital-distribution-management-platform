<?php

namespace Tests\Unit;

use App\Models\DriverProfile;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_belongs_to_user_and_territory(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $territory = Territory::create(['name' => 'Jakarta Selatan', 'code' => 'JKS']);

        $profile = DriverProfile::factory()->create([
            'user_id' => $driver->id,
            'service_territory_id' => $territory->id,
        ]);

        $this->assertInstanceOf(DriverProfile::class, $profile);
        $this->assertTrue($profile->user->is($driver));
        $this->assertTrue($profile->serviceTerritory->is($territory));
    }

    public function test_user_exposes_driver_profile_relation(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $this->assertNull($driver->driverProfile);

        $profile = DriverProfile::factory()->create(['user_id' => $driver->id]);

        $this->assertInstanceOf(DriverProfile::class, $driver->fresh()->driverProfile);
        $this->assertTrue($driver->fresh()->driverProfile->is($profile));
    }

    public function test_capacity_and_availability_are_cast(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $profile = DriverProfile::factory()->create([
            'user_id' => $driver->id,
            'capacity_kg' => 120,
            'is_available' => false,
        ])->fresh();

        $this->assertSame(120, $profile->capacity_kg);
        $this->assertFalse($profile->is_available);
    }

    public function test_factory_generates_valid_plate_number(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $profile = DriverProfile::factory()->create(['user_id' => $driver->id]);

        $this->assertMatchesRegularExpression('/^[A-Z]{1,2} \d{1,4} [A-Z]{1,3}$/', $profile->plate_number);
    }
}
