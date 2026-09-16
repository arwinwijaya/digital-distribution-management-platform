<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOutletsTest extends TestCase
{
    use RefreshDatabase;

    private function bearerFor(User $user): string
    {
        $token = app(\App\Services\AuthService::class)->createToken($user)['token'];

        return 'Bearer ' . $token;
    }

    private function makeTerritory(): \App\Models\Territory
    {
        return \App\Models\Territory::create(['name' => 'Territory Test', 'code' => 'territory-test']);
    }

    private function makeSalesUser(?int $territoryId): User
    {
        return User::factory()->sales()->create([
            'is_active'    => true,
            'territory_id' => $territoryId,
        ]);
    }

    // =================================================================
    // Happy path
    // =================================================================

    public function test_sales_user_with_territory_gets_filtered_outlets(): void
    {
        $territory = $this->makeTerritory();
        $sales = $this->makeSalesUser($territory->id);

        $own = Outlet::factory()->create(['territory_id' => $territory->id, 'is_active' => true, 'name' => 'Alpha Outlet']);
        $other = Outlet::factory()->create(['territory_id' => null, 'is_active' => true, 'name' => 'Zeta Outlet']);

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/sales/outlets');

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_response_includes_expected_fields_only(): void
    {
        $territory = $this->makeTerritory();
        $sales = $this->makeSalesUser($territory->id);

        Outlet::factory()->create(['territory_id' => $territory->id, 'is_active' => true]);

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/sales/outlets');

        $response->assertOk();
        $outlet = $response->json('data')[0];
        $this->assertArrayHasKey('id', $outlet);
        $this->assertArrayHasKey('name', $outlet);
        $this->assertArrayHasKey('category', $outlet);
        $this->assertArrayHasKey('territory_id', $outlet);
        $this->assertArrayNotHasKey('score', $outlet, 'Should not expose score field');
    }

    public function test_outlets_ordered_by_name(): void
    {
        $territory = $this->makeTerritory();
        $sales = $this->makeSalesUser($territory->id);

        Outlet::factory()->create(['territory_id' => $territory->id, 'is_active' => true, 'name' => 'Zulu']);
        Outlet::factory()->create(['territory_id' => $territory->id, 'is_active' => true, 'name' => 'Alpha']);

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/sales/outlets');

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Alpha', 'Zulu'], $names);
    }

    // =================================================================
    // Authorization / edge cases
    // =================================================================

    public function test_sales_user_without_territory_gets_403(): void
    {
        $sales = $this->makeSalesUser(null);

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/sales/outlets');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Sales user must be assigned to a territory.');
    }

    public function test_outlet_user_gets_403(): void
    {
        $outletUser = User::factory()->outlet()->create(['is_active' => true]);

        $this->withHeader('Authorization', $this->bearerFor($outletUser))
            ->getJson('/api/sales/outlets')
            ->assertStatus(403);
    }

    public function test_admin_user_gets_403(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson('/api/sales/outlets')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/sales/outlets')
            ->assertStatus(401);
    }
}
