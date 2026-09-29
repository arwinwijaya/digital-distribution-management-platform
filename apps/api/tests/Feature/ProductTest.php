<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
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
        'test_admin_product_list_matches_frontend_clarity_contract_across_filters_sorts_and_legacy_eligibility',
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

        if ($this->allowOrphanSupplierFixtures) {
            $this->beforeApplicationDestroyed(function () {
                foreach (RefreshDatabaseState::$inMemoryConnections as $pdo) {
                    if ($pdo instanceof \PDO) {
                        $pdo->exec('PRAGMA foreign_keys = ON');
                    }
                }

                try {
                    $this->app->make('db')->connection()->getSchemaBuilder()->enableForeignKeyConstraints();
                } catch (\Throwable) {
                    // RefreshDatabase disconnects the live connection first;
                    // the cached PDO restore above prevents FK leakage.
                }
            });
        }
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

    public function test_no_category_sentinel_filters_null_and_empty_categories_with_other_filters(): void
    {
        $activeSupplier = Supplier::factory()->active()->create();
        $inactiveSupplier = Supplier::factory()->create(['subscription_status' => 'inactive']);

        Product::factory()->create([
            'name' => 'Matching null category',
            'category' => null,
            'is_active' => true,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'name' => 'Matching empty category',
            'category' => '  ',
            'is_active' => true,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'name' => 'Wrong status no category',
            'category' => null,
            'is_active' => false,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'name' => 'Wrong stock no category',
            'category' => '',
            'is_active' => true,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 11,
        ]);
        Product::factory()->create([
            'name' => 'Categorized low active',
            'category' => 'Minuman',
            'is_active' => true,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'name' => 'Unpurchasable no category',
            'category' => null,
            'is_active' => true,
            'supplier_id' => $inactiveSupplier->id,
            'stock_quantity' => 5,
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson(
            '/api/products?include_unpurchasable=1&category=__none__&status=active&stock_health=low'
        );

        $response->assertOk();
        $this->assertSame(
            ['Matching null category', 'Matching empty category', 'Unpurchasable no category'],
            collect($response->json('data'))->pluck('name')->all()
        );
        $this->assertSame(3, $response->json('meta.total'));
        // meta.categories filters to non-empty, trimmed, distinct categories; __none__ selects null/empty only
        $this->assertSame([], $response->json('meta.categories'));
    }

    public function test_category_status_and_stock_health_filters_use_and_semantics_and_filtered_summary(): void
    {
        $activeSupplier = Supplier::factory()->active()->create();
        $inactiveSupplier = Supplier::factory()->create(['subscription_status' => 'inactive']);

        Product::factory()->create([
            'name' => 'Matching low active drink',
            'category' => 'Minuman',
            'is_active' => true,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 10,
        ]);
        Product::factory()->create([
            'name' => 'Wrong stock drink',
            'category' => 'Minuman',
            'is_active' => true,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 11,
        ]);
        Product::factory()->create([
            'name' => 'Wrong category low active',
            'category' => 'Sembako',
            'is_active' => true,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'name' => 'Inactive low drink',
            'category' => 'Minuman',
            'is_active' => false,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'name' => 'Unpurchasable low drink',
            'category' => 'Minuman',
            'is_active' => true,
            'supplier_id' => $inactiveSupplier->id,
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'name' => 'Matching out drink',
            'category' => 'Minuman',
            'is_active' => true,
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders())->getJson(
            '/api/products?include_unpurchasable=1&category=Minuman&status=active&stock_health=low'
        );

        $response->assertOk();
        $this->assertSame(['Matching low active drink', 'Unpurchasable low drink'], collect($response->json('data'))->pluck('name')->all());
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.summary.total'));
        $this->assertSame(0, $response->json('meta.summary.out_of_stock'));
        $this->assertSame(['Minuman'], $response->json('meta.categories'));
    }

    public function test_invalid_scalar_array_and_malformed_query_values_are_ignored_with_200(): void
    {
        $activeSupplier = Supplier::factory()->active()->create();

        Product::factory()->create([
            'name' => 'Valid low drink',
            'category' => 'Minuman',
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 5,
        ]);
        Product::factory()->create([
            'name' => 'Other product',
            'category' => 'Sembako',
            'supplier_id' => $activeSupplier->id,
            'stock_quantity' => 5,
        ]);

        $headers = $this->authHeaders();

        // Unknown scalar values must not filter anything out and never error.
        $unknown = $this->withHeaders($headers)->getJson(
            '/api/products?include_unpurchasable=1&category=__nope__&status=bogus&stock_health=never'
        );
        $unknown->assertOk();
        $this->assertCount(0, $unknown->json('data'));
        $this->assertSame(0, $unknown->json('meta.total'));

        // Array/malformed forms of every new param fall back safely to the unfiltered set.
        $arrays = $this->withHeaders($headers)->getJson(
            '/api/products?include_unpurchasable=1&category[]=Minuman&status[]=active&stock_health[]=low&sort[]=name&category[foo]=Minuman'
        );
        $arrays->assertOk();
        $this->assertCount(2, $arrays->json('data'));
        $this->assertSame(2, $arrays->json('meta.total'));

        // Invalid sort/order still resolve to the legacy default without a 500.
        $sort = $this->withHeaders($headers)->getJson(
            '/api/products?include_unpurchasable=1&sort=__proto__&order=sideways&category=Minuman'
        );
        $sort->assertOk();
        $this->assertSame(['Valid low drink'], collect($sort->json('data'))->pluck('name')->all());
    }

    public function test_category_and_status_sort_contracts_are_deterministic_with_id_desc_ties(): void
    {
        $activeSupplier = Supplier::factory()->active()->create();
        $inactiveSupplier = Supplier::factory()->create(['subscription_status' => 'inactive']);

        Product::factory()->create(['name' => 'Cat beta aktif', 'category' => 'beta', 'is_active' => true, 'supplier_id' => $activeSupplier->id]);
        Product::factory()->create(['name' => 'Cat Alpha aktif 1', 'category' => 'Alpha', 'is_active' => true, 'supplier_id' => $activeSupplier->id]);
        Product::factory()->create(['name' => 'Cat null aktif', 'category' => null, 'is_active' => true, 'supplier_id' => null]);
        Product::factory()->create(['name' => 'Cat empty aktif', 'category' => '', 'is_active' => true, 'supplier_id' => null]);
        Product::factory()->create(['name' => 'Cat Alpha aktif 2', 'category' => 'Alpha', 'is_active' => true, 'supplier_id' => $activeSupplier->id]);
        Product::factory()->create(['name' => 'Cat padded Alpha aktif', 'category' => '  Alpha  ', 'is_active' => true, 'supplier_id' => $activeSupplier->id]);
        Product::factory()->create(['name' => 'Cat Sembako unpurchasable 1', 'category' => 'Sembako', 'is_active' => true, 'supplier_id' => $inactiveSupplier->id]);
        Product::factory()->create(['name' => 'Cat Sembako unpurchasable 2', 'category' => 'Sembako', 'is_active' => true, 'supplier_id' => $inactiveSupplier->id]);
        Product::factory()->create(['name' => 'Cat Zebra nonaktif 1', 'category' => 'Zebra', 'is_active' => false, 'supplier_id' => $activeSupplier->id]);
        Product::factory()->create(['name' => 'Cat Zebra nonaktif 2', 'category' => 'Zebra', 'is_active' => false, 'supplier_id' => null]);

        $headers = $this->authHeaders();
        $names = fn ($response) => collect($response->json('data'))->pluck('name')->all();

        // Category ASC: trim-normalized alphabetic, null/empty last, id DESC ties.
        $categoryAsc = $this->withHeaders($headers)->getJson('/api/products?include_unpurchasable=1&sort=category&order=asc');
        $categoryAsc->assertOk();
        $this->assertSame([
            'Cat padded Alpha aktif',
            'Cat Alpha aktif 2',
            'Cat Alpha aktif 1',
            'Cat beta aktif',
            'Cat Sembako unpurchasable 2',
            'Cat Sembako unpurchasable 1',
            'Cat Zebra nonaktif 2',
            'Cat Zebra nonaktif 1',
            'Cat empty aktif',
            'Cat null aktif',
        ], $names($categoryAsc));

        // Category DESC: reversed values, null/empty STILL last, id DESC ties.
        $categoryDesc = $this->withHeaders($headers)->getJson('/api/products?include_unpurchasable=1&sort=category&order=desc');
        $categoryDesc->assertOk();
        $this->assertSame([
            'Cat Zebra nonaktif 2',
            'Cat Zebra nonaktif 1',
            'Cat Sembako unpurchasable 2',
            'Cat Sembako unpurchasable 1',
            'Cat beta aktif',
            'Cat padded Alpha aktif',
            'Cat Alpha aktif 2',
            'Cat Alpha aktif 1',
            'Cat empty aktif',
            'Cat null aktif',
        ], $names($categoryDesc));

        // Status ASC: Aktif -> Tidak bisa dibeli -> Nonaktif, id DESC ties.
        $statusAsc = $this->withHeaders($headers)->getJson('/api/products?include_unpurchasable=1&sort=status&order=asc');
        $statusAsc->assertOk();
        $this->assertSame([
            'Cat padded Alpha aktif',
            'Cat Alpha aktif 2',
            'Cat empty aktif',
            'Cat null aktif',
            'Cat Alpha aktif 1',
            'Cat beta aktif',
            'Cat Sembako unpurchasable 2',
            'Cat Sembako unpurchasable 1',
            'Cat Zebra nonaktif 2',
            'Cat Zebra nonaktif 1',
        ], $names($statusAsc));

        // Status DESC: Nonaktif -> Tidak bisa dibeli -> Aktif, id DESC ties.
        $statusDesc = $this->withHeaders($headers)->getJson('/api/products?include_unpurchasable=1&sort=status&order=desc');
        $statusDesc->assertOk();
        $this->assertSame([
            'Cat Zebra nonaktif 2',
            'Cat Zebra nonaktif 1',
            'Cat Sembako unpurchasable 2',
            'Cat Sembako unpurchasable 1',
            'Cat padded Alpha aktif',
            'Cat Alpha aktif 2',
            'Cat empty aktif',
            'Cat null aktif',
            'Cat Alpha aktif 1',
            'Cat beta aktif',
        ], $names($statusDesc));

        // Category metadata stays distinct, non-empty, trimmed and sorts case-insensitively.
        $this->assertSame(['Alpha', 'beta', 'Sembako', 'Zebra'], $categoryAsc->json('meta.categories'));
        $this->assertSame(10, $categoryAsc->json('meta.total'));
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

    public function test_admin_product_list_matches_frontend_clarity_contract_across_filters_sorts_and_legacy_eligibility(): void
    {
        $activeSupplier = Supplier::factory()->active()->create(['name' => 'PT Segar']);
        $inactiveSupplier = Supplier::factory()->create([
            'name' => 'CV Expired',
            'subscription_status' => 'inactive',
        ]);

        Product::factory()->create([
            'name' => 'Active Minuman',
            'category' => 'Minuman',
            'stock_quantity' => 5,
            'supplier_id' => $activeSupplier->id,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Expired Minuman',
            'category' => 'Minuman',
            'stock_quantity' => 0,
            'supplier_id' => $inactiveSupplier->id,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Inactive Minuman',
            'category' => 'Minuman',
            'stock_quantity' => 20,
            'supplier_id' => $activeSupplier->id,
            'is_active' => false,
        ]);
        Product::factory()->create([
            'name' => 'No Supplier',
            'category' => null,
            'stock_quantity' => 0,
            'supplier_id' => null,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Orphan Supplier',
            'category' => 'Sembako',
            'stock_quantity' => 5,
            'supplier_id' => 999999,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'History Product',
            'category' => 'Zeta',
            'stock_quantity' => 40,
            'supplier_id' => $activeSupplier->id,
            'is_active' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $headers = $this->authHeaders();
        $admin = $this->withHeaders($headers)->getJson(
            '/api/products?include_unpurchasable=1&sort=status&order=asc&limit=100&cursor=0'
        );

        $admin->assertOk()->assertJsonStructure([
            'status',
            'data' => ['*' => [
                'id', 'supplier_id', 'name', 'description', 'price', 'sku',
                'stock_quantity', 'category', 'is_active', 'created_at', 'updated_at', 'supplier',
            ]],
            'meta' => ['has_more', 'limit', 'cursor', 'total', 'summary', 'categories'],
        ]);
        $this->assertSame(['status', 'data', 'meta'], array_keys($admin->json()));
        $this->assertSame(['has_more', 'limit', 'cursor', 'total', 'summary', 'categories'], array_keys($admin->json('meta')));
        $this->assertSame(['total', 'out_of_stock'], array_keys($admin->json('meta.summary')));
        $this->assertSame(6, $admin->json('meta.total'));
        $this->assertSame(6, $admin->json('meta.summary.total'));
        $this->assertSame(2, $admin->json('meta.summary.out_of_stock'));
        $this->assertSame(100, $admin->json('meta.limit'));
        $this->assertSame(0, $admin->json('meta.cursor'));
        $this->assertFalse($admin->json('meta.has_more'));
        $this->assertSame(['Minuman', 'Sembako', 'Zeta'], $admin->json('meta.categories'));

        $rows = collect($admin->json('data'))->keyBy('name');
        // Exact envelope field SET per row (adapter's AdminProduct contract);
        // attribute order follows the physical schema and is not part of it.
        $this->assertEqualsCanonicalizing(
            ['id', 'supplier_id', 'name', 'description', 'price', 'sku', 'stock_quantity', 'category', 'is_active', 'created_at', 'updated_at', 'supplier'],
            array_keys($rows->get('Active Minuman'))
        );
        $this->assertSame(
            ['id', 'name', 'subscription_status'],
            array_keys($rows->get('Active Minuman')['supplier'])
        );
        $this->assertSame([
            'id' => $activeSupplier->id,
            'name' => 'PT Segar',
            'subscription_status' => 'active',
        ], $rows->get('Active Minuman')['supplier']);
        $this->assertNull($rows->get('No Supplier')['supplier']);
        $this->assertNull($rows->get('Orphan Supplier')['supplier']);

        // Status sort is a real response ordering consumed by the adapter/page:
        // priority Aktif(0) -> supplier-ineligible(1) -> Nonaktif(2), id DESC ties.
        $this->assertSame([
            'History Product', 'Orphan Supplier', 'No Supplier', 'Active Minuman',
            'Expired Minuman', 'Inactive Minuman',
        ], collect($admin->json('data'))->pluck('name')->all());

        $category = $this->withHeaders($headers)->getJson(
            '/api/products?include_unpurchasable=1&sort=category&order=asc&limit=100&cursor=0'
        );
        $category->assertOk();
        // Trimmed alphabetic, null/empty last, id DESC ties within a value.
        $this->assertSame([
            'Inactive Minuman', 'Expired Minuman', 'Active Minuman', 'Orphan Supplier',
            'History Product', 'No Supplier',
        ], collect($category->json('data'))->pluck('name')->all());

        // Cursor pagination contract: has_more=true beyond the page, cursor echoes the offset.
        $page = $this->withHeaders($headers)->getJson('/api/products?include_unpurchasable=1&limit=2&cursor=0&sort=id&order=asc');
        $page->assertOk();
        $this->assertSame(['Active Minuman', 'Expired Minuman'], collect($page->json('data'))->pluck('name')->all());
        $this->assertTrue($page->json('meta.has_more'));
        $this->assertSame(2, $page->json('meta.limit'));
        $this->assertSame(0, $page->json('meta.cursor'));
        $this->assertSame(6, $page->json('meta.total'));

        $lastPage = $this->withHeaders($headers)->getJson('/api/products?include_unpurchasable=1&limit=2&cursor=4&sort=id&order=asc');
        $lastPage->assertOk();
        $this->assertSame(['Orphan Supplier', 'History Product'], collect($lastPage->json('data'))->pluck('name')->all());
        $this->assertFalse($lastPage->json('meta.has_more'));
        $this->assertSame(4, $lastPage->json('meta.cursor'));

        $filtered = $this->withHeaders($headers)->getJson(
            '/api/products?include_unpurchasable=1&category=Minuman&status=active&stock_health=low&sort=status&order=asc'
        );
        $filtered->assertOk();
        $this->assertSame(['Active Minuman'], collect($filtered->json('data'))->pluck('name')->all());
        $this->assertSame(1, $filtered->json('meta.total'));
        $this->assertSame(['total' => 1, 'out_of_stock' => 0], $filtered->json('meta.summary'));

        // Omitting the admin flag preserves supplier eligibility: products of a
        // non-active supplier and orphan-supplier rows are absent from the
        // legacy list (the status/stock filters keep the non-active product out).
        $legacy = $this->withHeaders($headers)->getJson('/api/products?limit=100');
        $legacy->assertOk();
        $this->assertSame([
            'Active Minuman', 'Inactive Minuman', 'No Supplier', 'History Product',
        ], collect($legacy->json('data'))->pluck('name')->all());
        $this->assertFalse(collect($legacy->json('data'))->contains('Expired Minuman'));
        $this->assertFalse(collect($legacy->json('data'))->contains('Orphan Supplier'));

        // Same clarity-filtered request without the admin flag: the ineligible
        // and orphan rows are excluded exactly as legacy requires.
        $legacyFiltered = $this->withHeaders($headers)->getJson(
            '/api/products?category=Minuman&status=active&stock_health=low&sort=status&order=asc'
        );
        $legacyFiltered->assertOk();
        $this->assertSame(['Active Minuman'], collect($legacyFiltered->json('data'))->pluck('name')->all());
        $this->assertSame(1, $legacyFiltered->json('meta.total'));
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
