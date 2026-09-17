<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Given products exist, When browsing catalog, Then products are displayed with prices
     */
    public function test_products_are_displayed_with_prices(): void
    {
        Product::factory()->create([
            'name' => 'Indomie Goreng',
            'price' => 3500,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Indomie Kuah Soto',
            'price' => 3500,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Teh Pucuk 350ml',
            'price' => 4000,
            'stock_quantity' => 200,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                        'is_active',
                    ],
                ],
            ])
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_product_availability_is_displayed(): void
    {
        Product::factory()->create([
            'name' => 'Active Product',
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Inactive Product',
            'stock_quantity' => 0,
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/products');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $activeProduct = collect($data)->firstWhere('name', 'Active Product');
        $this->assertTrue($activeProduct['is_active']);

        $inactiveProduct = collect($data)->firstWhere('name', 'Inactive Product');
        $this->assertFalse($inactiveProduct['is_active']);
    }

    public function test_products_can_be_searched_by_name(): void
    {
        Product::factory()->create(['name' => 'Indomie Goreng', 'price' => 3500, 'is_active' => true]);
        Product::factory()->create(['name' => 'Indomie Kuah', 'price' => 3500, 'is_active' => true]);
        Product::factory()->create(['name' => 'Teh Pucuk', 'price' => 4000, 'is_active' => true]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/products?search=Indomie');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $product) {
            $this->assertStringContainsString('Indomie', $product['name']);
        }
    }

    public function test_inactive_supplier_products_are_not_exposed_in_legacy_catalog(): void
    {
        $inactiveSupplier = Supplier::factory()->create(['subscription_status' => 'inactive']);
        Product::factory()->create(['supplier_id' => $inactiveSupplier->id, 'name' => 'Hidden product']);
        Product::factory()->create(['name' => 'Legacy product']);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/products');

        $response->assertOk();
        $this->assertSame(['Legacy product'], collect($response->json('data'))->pluck('name')->all());
    }

    public function test_products_default_ordering_is_id_asc_and_meta_total_is_additive(): void
    {
        Product::factory()->create(['name' => 'Alpha']);
        Product::factory()->create(['name' => 'Bravo']);
        Product::factory()->create(['name' => 'Charlie']);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        // Legacy default ordering stays id ASC (marketplace consumers depend on it).
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame(collect($ids)->sort()->values()->all(), $ids);

        // Additive meta.total mirrors the unfiltered catalog count.
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(100, $response->json('meta.limit'));
        $this->assertSame(0, $response->json('meta.cursor'));
        $this->assertFalse($response->json('meta.has_more'));
    }

    public function test_products_can_be_sorted_by_created_at_desc_with_nulls_last(): void
    {
        $oldest = Product::factory()->create(['name' => 'Oldest', 'created_at' => now()->subDays(3)]);
        $middle = Product::factory()->create(['name' => 'Middle', 'created_at' => now()->subDays(2)]);
        $newest = Product::factory()->create(['name' => 'Newest', 'created_at' => now()->subDay()]);
        $undated = Product::factory()->create(['name' => 'Undated', 'created_at' => null]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?sort=created_at&order=desc');

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertSame(['Newest', 'Middle', 'Oldest', 'Undated'], $names);
        $this->assertSame(4, $response->json('meta.total'));
    }

    public function test_empty_catalog_returns_empty_array(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [],
            ]);
    }

    public function test_products_require_authentication(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
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
