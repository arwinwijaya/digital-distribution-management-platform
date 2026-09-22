<?php

namespace Tests\Feature\FieldOps;

use App\Models\DriverProfile;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 8 — T5: admin driver roster CRUD (`/admin/drivers`).
 *
 * Covers the AC-1 contract: create with driver-role validation, duplicate
 * profile rejection, authorization (401/403), list pagination/sort/summary and
 * patch updates. Deletion removes the roster profile but keeps the user.
 */
class DriverRosterTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user, string $password = 'password'): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->json('data.token');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'email' => 'admin-roster@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function driver(): User
    {
        return User::factory()->driver()->create([
            'email' => 'driver-roster-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    // =====================================================================
    // Store
    // =====================================================================

    public function test_admin_can_create_a_driver_profile(): void
    {
        $admin = $this->admin();
        $driver = $this->driver();
        $territory = Territory::create(['name' => 'Jakarta Selatan', 'code' => 'JKS']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->login($admin))
            ->postJson('/api/admin/drivers', [
                'user_id' => $driver->id,
                'vehicle_type' => 'motor',
                'plate_number' => 'B 1234 XYZ',
                'capacity_kg' => 50,
                'service_territory_id' => $territory->id,
                'shift_start' => '08:00',
                'shift_end' => '17:00',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $driver->id)
            ->assertJsonPath('data.vehicle_type', 'motor')
            ->assertJsonPath('data.plate_number', 'B 1234 XYZ')
            ->assertJsonPath('data.capacity_kg', 50)
            ->assertJsonPath('data.service_territory_id', $territory->id)
            ->assertJsonPath('data.is_available', true);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $driver->id,
            'plate_number' => 'B 1234 XYZ',
        ]);
    }

    public function test_store_rejects_a_non_driver_user(): void
    {
        $admin = $this->admin();
        $sales = User::factory()->sales()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->login($admin))
            ->postJson('/api/admin/drivers', [
                'user_id' => $sales->id,
                'vehicle_type' => 'motor',
                'plate_number' => 'B 1234 XYZ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_store_rejects_a_driver_who_already_has_a_profile(): void
    {
        $admin = $this->admin();
        $driver = $this->driver();
        DriverProfile::factory()->create(['user_id' => $driver->id]);

        $this->withHeader('Authorization', 'Bearer '.$this->login($admin))
            ->postJson('/api/admin/drivers', [
                'user_id' => $driver->id,
                'vehicle_type' => 'mobil',
                'plate_number' => 'B 9999 ABC',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_store_rejects_a_duplicate_plate_number(): void
    {
        $admin = $this->admin();
        DriverProfile::factory()->create(['plate_number' => 'B 1234 XYZ']);

        $this->withHeader('Authorization', 'Bearer '.$this->login($admin))
            ->postJson('/api/admin/drivers', [
                'user_id' => $this->driver()->id,
                'vehicle_type' => 'motor',
                'plate_number' => 'B 1234 XYZ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plate_number']);
    }

    // =====================================================================
    // Authorization
    // =====================================================================

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/admin/drivers', [
            'user_id' => $this->driver()->id,
            'vehicle_type' => 'motor',
            'plate_number' => 'B 1234 XYZ',
        ])->assertStatus(401);
    }

    public function test_sales_user_is_forbidden(): void
    {
        $sales = User::factory()->sales()->create([
            'email' => 'sales-roster@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->login($sales))
            ->getJson('/api/admin/drivers')
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer '.$this->login($sales))
            ->postJson('/api/admin/drivers', [
                'user_id' => $this->driver()->id,
                'vehicle_type' => 'motor',
                'plate_number' => 'B 1234 XYZ',
            ])
            ->assertStatus(403);
    }

    public function test_platform_owner_can_access_the_roster(): void
    {
        $owner = User::factory()->platformOwner()->create([
            'email' => 'owner-roster@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->login($owner))
            ->getJson('/api/admin/drivers')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    // =====================================================================
    // Index contract
    // =====================================================================

    public function test_index_paginates_with_total_and_summary(): void
    {
        $admin = $this->admin();
        DriverProfile::factory()->count(25)->create();
        DriverProfile::factory()->unavailable()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->login($admin))
            ->getJson('/api/admin/drivers?limit=10');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 28)
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.cursor', 0)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.summary.available', 25)
            ->assertJsonPath('meta.summary.unavailable', 3)
            ->assertJsonCount(10, 'data');
    }

    public function test_index_sorts_by_plate_number_when_requested(): void
    {
        $admin = $this->admin();
        DriverProfile::factory()->create(['plate_number' => 'B 3000 CCC']);
        DriverProfile::factory()->create(['plate_number' => 'B 1000 AAA']);
        DriverProfile::factory()->create(['plate_number' => 'B 2000 BBB']);

        $this->withHeader('Authorization', 'Bearer '.$this->login($admin))
            ->getJson('/api/admin/drivers?sort=plate_number&order=asc')
            ->assertOk()
            ->assertJsonPath('data.0.plate_number', 'B 1000 AAA')
            ->assertJsonPath('data.1.plate_number', 'B 2000 BBB')
            ->assertJsonPath('data.2.plate_number', 'B 3000 CCC');
    }

    // =====================================================================
    // Update + destroy
    // =====================================================================

    public function test_admin_can_update_a_driver_profile(): void
    {
        $admin = $this->admin();
        $profile = DriverProfile::factory()->create(['vehicle_type' => 'motor']);

        $this->withHeader('Authorization', 'Bearer '.$this->login($admin))
            ->patchJson('/api/admin/drivers/'.$profile->id, [
                'vehicle_type' => 'mobil',
                'is_available' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.vehicle_type', 'mobil')
            ->assertJsonPath('data.is_available', false);

        $this->assertDatabaseHas('driver_profiles', [
            'id' => $profile->id,
            'vehicle_type' => 'mobil',
            'is_available' => false,
        ]);
    }

    public function test_admin_can_delete_a_driver_profile_but_keeps_the_user(): void
    {
        $admin = $this->admin();
        $driver = $this->driver();
        $profile = DriverProfile::factory()->create(['user_id' => $driver->id]);

        $this->withHeader('Authorization', 'Bearer '.$this->login($admin))
            ->deleteJson('/api/admin/drivers/'.$profile->id)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('driver_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('users', ['id' => $driver->id]);
    }
}
