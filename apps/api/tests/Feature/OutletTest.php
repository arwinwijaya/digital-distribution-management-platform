<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OutletTest extends TestCase
{
    use RefreshDatabase;

    public function test_outlet_can_register_with_valid_data(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/outlets', [
            'name' => 'Toko Berkah',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka No. 10',
            'city' => 'Jakarta',
            'district' => 'Menteng',
        ]);

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

        $this->assertDatabaseHas('outlets', [
            'name' => 'Toko Berkah',
            'phone' => '081234567890',
        ]);
    }

    public function test_outlet_cannot_register_with_duplicate_phone(): void
    {
        Outlet::factory()->create([
            'phone' => '081234567890',
        ]);

        $response = $this->withHeaders($this->authHeaders())->postJson('/api/outlets', [
            'name' => 'Toko Baru',
            'phone' => '081234567890',
            'address' => 'Jl. Sudirman No. 20',
            'city' => 'Bandung',
            'district' => 'Coblong',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_outlet_cannot_register_without_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())->postJson('/api/outlets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone', 'address', 'city', 'district']);
    }

    public function test_legacy_outlet_route_requires_authentication(): void
    {
        $this->postJson('/api/outlets', [
            'name' => 'Unauthenticated Outlet',
            'phone' => '081234567890',
            'address' => 'Jl. Public',
            'city' => 'Jakarta',
            'district' => 'Menteng',
        ])->assertUnauthorized();
    }

    private function authHeaders(): array
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->json('data.token');

        return ['Authorization' => 'Bearer '.$token];
    }
}
