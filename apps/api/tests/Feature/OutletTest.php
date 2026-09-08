<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OutletTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Given valid outlet data, When registering, Then outlet profile is created
     */
    public function test_outlet_can_register_with_valid_data(): void
    {
        // Act: POST /api/outlets with valid outlet data
        $response = $this->postJson('/api/outlets', [
            'name' => 'Toko Berkah',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 10',
            'city' => 'Jakarta',
            'district' => 'Menteng',
        ]);

        // Assert: Outlet is created and returned
        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'name',
                    'phone',
                    'address',
                    'city',
                    'district',
                    'is_active',
                    'created_at',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'name' => 'Toko Berkah',
                    'phone' => '081234567890',
                    'is_active' => true,
                ],
            ]);

        // Verify outlet exists in database
        $this->assertDatabaseHas('outlets', [
            'name' => 'Toko Berkah',
            'phone' => '081234567890',
        ]);
    }

    /**
     * Test: Given duplicate phone number, When registering, Then validation error is returned
     */
    public function test_outlet_cannot_register_with_duplicate_phone(): void
    {
        // Arrange: Create an outlet with the phone number first
        Outlet::factory()->create([
            'phone' => '081234567890',
        ]);

        // Act: POST /api/outlets with the same phone number
        $response = $this->postJson('/api/outlets', [
            'name' => 'Toko Baru',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 20',
            'city' => 'Bandung',
            'district' => 'Coblong',
        ]);

        // Assert: 422 validation error for duplicate phone
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * Test: Given missing required fields, When registering, Then validation error is returned
     */
    public function test_outlet_cannot_register_without_required_fields(): void
    {
        // Act: POST /api/outlets without required fields
        $response = $this->postJson('/api/outlets', []);

        // Assert: 422 validation error for missing required fields
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone', 'address', 'city', 'district']);
    }
}
