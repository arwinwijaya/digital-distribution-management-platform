<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Given products exist, When browsing catalog, Then products are displayed with prices
     */
    public function test_products_are_displayed_with_prices(): void
    {
        // Arrange: Create some products
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

        // Act: GET /api/products
        $response = $this->getJson('/api/products');

        // Assert: Products are returned with required fields
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

        // Verify the count
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test: Given products with varying availability, When browsing catalog, Then availability is shown
     */
    public function test_product_availability_is_displayed(): void
    {
        // Arrange: Create an active and an inactive product
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

        // Act: GET /api/products
        $response = $this->getJson('/api/products');

        // Assert: Both products are returned (catalog shows all)
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Verify availability info is present
        $activeProduct = collect($data)->firstWhere('name', 'Active Product');
        $this->assertTrue($activeProduct['is_active']);

        $inactiveProduct = collect($data)->firstWhere('name', 'Inactive Product');
        $this->assertFalse($inactiveProduct['is_active']);
    }

    /**
     * Test: Given products exist, When searching by name, Then matching products are returned
     */
    public function test_products_can_be_searched_by_name(): void
    {
        // Arrange: Create products
        Product::factory()->create(['name' => 'Indomie Goreng', 'price' => 3500, 'is_active' => true]);
        Product::factory()->create(['name' => 'Indomie Kuah', 'price' => 3500, 'is_active' => true]);
        Product::factory()->create(['name' => 'Teh Pucuk', 'price' => 4000, 'is_active' => true]);

        // Act: GET /api/products?search=Indomie
        $response = $this->getJson('/api/products?search=Indomie');

        // Assert: Only matching products are returned
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // All returned products should contain "Indomie"
        foreach ($data as $product) {
            $this->assertStringContainsString('Indomie', $product['name']);
        }
    }

    /**
     * Test: Given no products exist, When browsing catalog, Then empty array is returned
     */
    public function test_empty_catalog_returns_empty_array(): void
    {
        // Act: GET /api/products with no products in DB
        $response = $this->getJson('/api/products');

        // Assert: 200 with empty data array
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [],
            ]);
    }
}
