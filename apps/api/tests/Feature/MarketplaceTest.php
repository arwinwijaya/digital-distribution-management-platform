<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_lists_active_suppliers_and_products_from_multiple_suppliers(): void
    {
        $activeOne = Supplier::factory()->active()->create(['name' => 'Alpha Supplier']);
        $activeTwo = Supplier::factory()->active()->create(['name' => 'Beta Supplier']);
        $inactive = Supplier::factory()->create(['name' => 'Hidden Supplier']);
        Product::factory()->create(['supplier_id' => $activeOne->id, 'name' => 'Alpha Rice', 'is_active' => true]);
        Product::factory()->create(['supplier_id' => $activeTwo->id, 'name' => 'Beta Tea', 'is_active' => true]);
        $hidden = Product::factory()->create(['supplier_id' => $inactive->id, 'name' => 'Hidden Tea', 'is_active' => true]);
        Product::factory()->create(['supplier_id' => $activeOne->id, 'name' => 'Disabled Rice', 'is_active' => false]);

        $headers = $this->authHeaders();
        $suppliers = $this->withHeaders($headers)->getJson('/api/marketplace/suppliers');
        $suppliers->assertOk()->assertJsonCount(2, 'data');
        $this->assertStringContainsString('no-store', (string) $suppliers->headers->get('Cache-Control'));
        $this->assertSame(['Alpha Supplier', 'Beta Supplier'], collect($suppliers->json('data'))->pluck('name')->all());

        $products = $this->withHeaders($headers)->getJson('/api/marketplace/products?per_page=50');
        $products->assertOk()->assertJsonCount(2, 'data');
        $this->assertStringContainsString('no-store', (string) $products->headers->get('Cache-Control'));
        $this->assertSame(['Alpha Supplier', 'Beta Supplier'], collect($products->json('data'))->pluck('supplier_name')->all());
        $this->assertNotContains($hidden->id, collect($products->json('data'))->pluck('id')->all());

        // No shared cache is used for this authenticated listing: a supplier
        // deactivation is visible immediately and cannot leak stale products.
        $activeOne->update(['subscription_status' => 'inactive']);
        $freshProducts = $this->withHeaders($headers)->getJson('/api/marketplace/products?per_page=50');
        $this->assertNotContains('Alpha Supplier', collect($freshProducts->json('data'))->pluck('supplier_name')->all());
    }

    public function test_marketplace_empty_state_is_a_stable_empty_page(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/marketplace/products');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_marketplace_query_indexes_are_present_for_listing_and_status_filters(): void
    {
        $supplierIndexes = collect(Schema::getIndexes('suppliers'))->pluck('name')->all();
        $productIndexes = collect(Schema::getIndexes('products'))->pluck('name')->all();

        $this->assertContains('suppliers_marketplace_status_name_index', $supplierIndexes);
        $this->assertContains('products_marketplace_listing_index', $productIndexes);
    }

    public function test_marketplace_requires_authentication_and_validates_bounds(): void
    {
        $this->getJson('/api/marketplace/products')->assertUnauthorized();
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/marketplace/products?per_page=1000')
            ->assertStatus(422);
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
