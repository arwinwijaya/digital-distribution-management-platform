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
    use RefreshDatabase {
        beginDatabaseTransaction as protected refreshBeginDatabaseTransaction;
    }

    /**
     * Tests that need a genuine orphan `products.supplier_id` fixture.
     *
     * @var array<int, string>
     */
    private const ORPHAN_FIXTURE_TESTS = [
        'test_include_unpurchasable_exposes_supplier_ineligible_products_without_changing_legacy_eligibility',
        'test_product_list_orphan_supplier_reference_serializes_supplier_as_null',
    ];

    private bool $allowOrphanSupplierFixtures = false;

    protected function setUp(): void
    {
        // Decided BEFORE parent::setUp() because RefreshDatabase opens the
        // per-test transaction there; SQLite's foreign-key PRAGMA is a no-op
        // once a transaction is active, so it must be toggled first.
        $this->allowOrphanSupplierFixtures = in_array($this->name(), self::ORPHAN_FIXTURE_TESTS, true);

        parent::setUp();
    }

    /**
     * SQLite enforces foreign keys connection-wide and the PRAGMA cannot be
     * changed inside a transaction, so disable it right before RefreshDatabase
     * opens the transaction for the orphan-fixture tests only. The FK remains
     * declared in the schema; this merely lets a legacy orphan reference exist.
     */
    public function beginDatabaseTransaction(): void
    {
        if ($this->allowOrphanSupplierFixtures) {
            $this->app->make('db')->connection()->getSchemaBuilder()->disableForeignKeyConstraints();
        }

        $this->refreshBeginDatabaseTransaction();
    }

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

    public function test_include_unpurchasable_exposes_supplier_ineligible_products_without_changing_legacy_eligibility(): void
    {
        $inactiveSupplier = Supplier::factory()->create(['subscription_status' => 'inactive']);
        $activeSupplier = Supplier::factory()->active()->create();

        Product::factory()->create(['supplier_id' => $activeSupplier->id, 'name' => 'Active supplier product']);
        Product::factory()->create(['supplier_id' => $inactiveSupplier->id, 'name' => 'Expired supplier product']);
        Product::factory()->create(['supplier_id' => 999999, 'name' => 'Orphan supplier product']);
        Product::factory()->create(['supplier_id' => null, 'name' => 'No supplier product']);
        Product::factory()->create(['supplier_id' => $inactiveSupplier->id, 'name' => 'Inactive product', 'is_active' => false]);

        $headers = $this->authHeaders();

        $legacy = $this->withHeaders($headers)->getJson('/api/products');
        $legacy->assertOk();
        $this->assertSame(
            ['Active supplier product', 'No supplier product'],
            collect($legacy->json('data'))->pluck('name')->all()
        );

        $admin = $this->withHeaders($headers)->getJson('/api/products?include_unpurchasable=1');
        $admin->assertOk();
        $this->assertSame(
            [
                'Active supplier product',
                'Expired supplier product',
                'Orphan supplier product',
                'No supplier product',
                'Inactive product',
            ],
            collect($admin->json('data'))->pluck('name')->all()
        );
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

    public function test_product_list_eager_loads_supplier_as_nested_object_and_handles_null_safely(): void
    {
        $supplier = Supplier::factory()->active()->create(['name' => 'PT Sumber Pangan']);
        Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'With supplier']);
        Product::factory()->create(['supplier_id' => null, 'name' => 'No supplier']);
        Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Second with supplier']);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/products');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));

        $byName = collect($response->json('data'))->keyBy('name');

        // Supplier must be present as a nested {id,name,subscription_status} object.
        $withSupplier = $byName->get('With supplier');
        $this->assertIsArray($withSupplier['supplier']);
        $this->assertSame(
            ['id', 'name', 'subscription_status'],
            array_keys($withSupplier['supplier'])
        );
        $this->assertSame($supplier->id, $withSupplier['supplier']['id']);
        $this->assertSame('PT Sumber Pangan', $withSupplier['supplier']['name']);
        $this->assertSame('active', $withSupplier['supplier']['subscription_status']);

        // Missing supplier must be null (no list failure, no N+1 crash).
        $this->assertNull($byName->get('No supplier')['supplier']);
    }

    public function test_product_list_orphan_supplier_reference_serializes_supplier_as_null(): void
    {
        $supplier = Supplier::factory()->active()->create();
        Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Normal']);
        Product::factory()->create(['supplier_id' => 999999, 'name' => 'Orphan']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?include_unpurchasable=1');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        $byName = collect($response->json('data'))->keyBy('name');
        $this->assertNull($byName->get('Orphan')['supplier']);
        $this->assertIsArray($byName->get('Normal')['supplier']);
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

    public function test_invalid_sort_falls_back_to_products_default_id_asc(): void
    {
        // id order deliberately diverges from created_at order so we can prove
        // the fallback is id ASC (products default) and NOT created_at DESC.
        $first = Product::factory()->create(['name' => 'First', 'created_at' => now()->subDays(3)]);
        $second = Product::factory()->create(['name' => 'Second', 'created_at' => now()->subDays(2)]);
        $third = Product::factory()->create(['name' => 'Third', 'created_at' => now()->subDay()]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?sort=__proto__&order=desc');

        $response->assertStatus(200);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            collect($response->json('data'))->pluck('id')->all()
        );
    }

    public function test_array_sort_param_falls_back_without_server_error(): void
    {
        Product::factory()->create(['name' => 'Alpha']);
        Product::factory()->create(['name' => 'Bravo']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?sort[]=name&order[]=asc&cursor[]=5');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame(collect($ids)->sort()->values()->all(), $ids);
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_meta_total_reflects_the_search_filter(): void
    {
        Product::factory()->create(['name' => 'Indomie Goreng']);
        Product::factory()->create(['name' => 'Indomie Kuah']);
        Product::factory()->create(['name' => 'Teh Pucuk']);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?search=Indomie');

        $response->assertStatus(200);

        $this->assertSame(2, $response->json('meta.total'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_meta_summary_reports_total_and_out_of_stock_for_the_filtered_set(): void
    {
        Product::factory()->create(['name' => 'Indomie Goreng', 'stock_quantity' => 10]);
        Product::factory()->create(['name' => 'Indomie Kuah', 'stock_quantity' => 0]);
        Product::factory()->create(['name' => 'Indomie Soto', 'stock_quantity' => -3]);
        Product::factory()->create(['name' => 'Teh Pucuk', 'stock_quantity' => 0]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/products?search=Indomie');

        $response->assertStatus(200);

        // Summary is ADDITIVE and mirrors the SAME filtered set as meta.total.
        $this->assertSame(3, $response->json('meta.summary.total'));
        $this->assertSame(2, $response->json('meta.summary.out_of_stock'));
        $this->assertSame($response->json('meta.total'), $response->json('meta.summary.total'));
    }

    public function test_meta_summary_excludes_products_of_inactive_suppliers(): void
    {
        $inactiveSupplier = Supplier::factory()->create(['subscription_status' => 'inactive']);
        Product::factory()->create(['supplier_id' => $inactiveSupplier->id, 'stock_quantity' => 0]);
        Product::factory()->create(['name' => 'Legacy in stock', 'stock_quantity' => 5]);
        Product::factory()->create(['name' => 'Legacy out of stock', 'stock_quantity' => 0]);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/products');

        $response->assertOk();
        $this->assertSame(2, $response->json('meta.summary.total'));
        $this->assertSame(1, $response->json('meta.summary.out_of_stock'));
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
