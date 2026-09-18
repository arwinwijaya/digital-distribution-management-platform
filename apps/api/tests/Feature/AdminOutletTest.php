<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Territory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOutletTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'admin-outlet-test@example.com';

    private string $adminPassword = 'password123';

    private function loginAsAdmin(): string
    {
        $admin = User::factory()->admin()->create([
            'email' => $this->adminEmail,
            'password' => Hash::make($this->adminPassword),
        ]);

        return $this->postJson('/api/auth/login', [
            'email' => $this->adminEmail,
            'password' => $this->adminPassword,
        ])->json('data.token');
    }

    private function loginAsOutletUser(): string
    {
        $user = User::factory()->outlet()->create([
            'email' => 'outlet-test-user@example.com',
            'password' => Hash::make('password123'),
        ]);

        return $this->postJson('/api/auth/login', [
            'email' => 'outlet-test-user@example.com',
            'password' => 'password123',
        ])->json('data.token');
    }

    // =====================================================================
    // 1) Outlet Listing with Filters + Pagination
    // =====================================================================

    /**
     * GWT: Given admin, When calling GET /admin/outlets, Then paginated list with name, category, territory, is_active
     */
    public function test_admin_can_list_outlets(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'name', 'phone', 'category', 'score'],
                ],
            ])
            ->assertJsonPath('status', 'success');
    }

    /**
     * GWT: Given admin with filter territory_id=1, Then only outlets in territory 1 returned
     */
    public function test_admin_can_filter_outlets_by_territory(): void
    {
        $token = $this->loginAsAdmin();

        $territory = Territory::create(['name' => 'Jaktim', 'code' => 'jaktim']);
        Outlet::factory()->create(['territory_id' => $territory->id, 'name' => 'Outlet A']);
        Outlet::factory()->create(['territory_id' => null, 'name' => 'Outlet B']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/admin/outlets?territory_id={$territory->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * GWT: Given admin with filter category=warung, Then only warung outlets returned
     */
    public function test_admin_can_filter_outlets_by_category(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->create(['name' => 'Warung A', 'category' => 'warung']);
        Outlet::factory()->create(['name' => 'Toko B', 'category' => 'toko_kelontong']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?category=warung');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * GWT: Given admin with filter is_active=1, Then only active outlets returned
     */
    public function test_admin_can_filter_outlets_by_is_active(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->create(['name' => 'Active', 'is_active' => true]);
        Outlet::factory()->create(['name' => 'Inactive', 'is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?is_active=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * GWT: Given admin, When searching with name, Then only matching outlets returned
     */
    public function test_admin_can_search_outlets_by_name(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->create(['name' => 'Alpha Store']);
        Outlet::factory()->create(['name' => 'Beta Market']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?search=Alpha');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * GWT: Given 25 outlets, When GET /admin/outlets?limit=10, Then returns 10 + has_more=true
     */
    public function test_admin_can_paginate_outlets(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->count(25)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?limit=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.has_more', true);
    }

    /**
     * GWT: Given outlets, When paginating to page 2, Then returns remaining + has_more=false
     */
    public function test_admin_can_paginate_outlets_page_2(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->count(15)->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?limit=10&cursor=10');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.has_more', false);
    }

    /**
     * GWT: Given outlets with varied created_at, When GET /admin/outlets (no sort),
     * Then newest created_at first (id DESC tiebreak) + meta.total + meta.summary.
     */
    public function test_admin_list_defaults_to_newest_first_with_total_and_summary(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $token = $this->loginAsAdmin();

        Outlet::factory()->create(['name' => 'Oldest Outlet', 'created_at' => '2026-01-01 08:00:00', 'is_active' => true]);
        Outlet::factory()->create(['name' => 'Newest Outlet', 'created_at' => '2026-03-01 08:00:00', 'is_active' => false]);
        Outlet::factory()->create(['name' => 'Middle Outlet', 'created_at' => '2026-02-01 08:00:00', 'is_active' => true]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?limit=15');

        $response->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.summary.active', 2)
            ->assertJsonPath('meta.summary.inactive', 1)
            ->assertJsonPath('data.0.name', 'Newest Outlet')
            ->assertJsonPath('data.1.name', 'Middle Outlet')
            ->assertJsonPath('data.2.name', 'Oldest Outlet');
    }

    /**
     * GWT: Given an invalid sort param, When GET /admin/outlets, Then HTTP 200 with
     * the default newest-first order (never 422/500).
     */
    public function test_admin_list_silently_falls_back_for_invalid_sort(): void
    {
        $token = $this->loginAsAdmin();

        Outlet::factory()->create(['name' => 'Older', 'created_at' => '2026-01-01 08:00:00']);
        Outlet::factory()->create(['name' => 'Newer', 'created_at' => '2026-03-01 08:00:00']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?sort=__proto__&order=desc');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.1.name', 'Older');
    }

    /**
     * GWT: Given a non-scalar (array) sort param, When GET /admin/outlets,
     * Then HTTP 200 with the default newest-first order (never 500).
     */
    public function test_admin_list_handles_non_scalar_sort_param_without_error(): void
    {
        $token = $this->loginAsAdmin();

        Outlet::factory()->create(['name' => 'Older', 'created_at' => '2026-01-01 08:00:00']);
        Outlet::factory()->create(['name' => 'Newer', 'created_at' => '2026-03-01 08:00:00']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?sort[]=x');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.1.name', 'Older');
    }

    /**
     * GWT: Given a non-scalar (array) order param, When GET /admin/outlets,
     * Then HTTP 200 with the default newest-first order (never 500).
     */
    public function test_admin_list_handles_non_scalar_order_param_without_error(): void
    {
        $token = $this->loginAsAdmin();

        Outlet::factory()->create(['name' => 'Older', 'created_at' => '2026-01-01 08:00:00']);
        Outlet::factory()->create(['name' => 'Newer', 'created_at' => '2026-03-01 08:00:00']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?order[]=x');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.1.name', 'Older');
    }

    /**
     * GWT: Given sort=name&order=asc, When GET /admin/outlets, Then outlets sorted by name asc.
     */
    public function test_admin_list_sorts_by_name_ascending(): void
    {
        $token = $this->loginAsAdmin();

        Outlet::factory()->create(['name' => 'Zebra Store']);
        Outlet::factory()->create(['name' => 'Alpha Store']);
        Outlet::factory()->create(['name' => 'Mango Store']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?sort=name&order=asc');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha Store')
            ->assertJsonPath('data.1.name', 'Mango Store')
            ->assertJsonPath('data.2.name', 'Zebra Store');
    }

    /**
     * GWT: Given an outlet with null created_at, When sorting by created_at asc or desc,
     * Then the null row is always last.
     */
    public function test_admin_list_places_null_created_at_rows_last_in_both_directions(): void
    {
        $token = $this->loginAsAdmin();

        Outlet::factory()->create(['name' => 'Dated Older', 'created_at' => '2026-01-01 08:00:00']);
        Outlet::factory()->create(['name' => 'Dated Newer', 'created_at' => '2026-03-01 08:00:00']);
        Outlet::factory()->create(['name' => 'Undated', 'created_at' => null]);

        $desc = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?sort=created_at&order=desc');
        $desc->assertOk()
            ->assertJsonPath('data.0.name', 'Dated Newer')
            ->assertJsonPath('data.1.name', 'Dated Older')
            ->assertJsonPath('data.2.name', 'Undated');

        $asc = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?sort=created_at&order=asc');
        $asc->assertOk()
            ->assertJsonPath('data.0.name', 'Dated Older')
            ->assertJsonPath('data.1.name', 'Dated Newer')
            ->assertJsonPath('data.2.name', 'Undated');
    }

    /**
     * GWT: Given 48 outlets, When GET /admin/outlets?limit=15, Then meta.has_more/limit/cursor
     * are top-level (cursor=0) and data is a top-level array; beyond-total cursor stays safe.
     */
    public function test_admin_list_normalizes_meta_to_top_level_and_handles_cursor_beyond_total(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->count(48)->create();

        $page1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?limit=15');

        $page1->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.limit', 15)
            ->assertJsonPath('meta.cursor', 0)
            ->assertJsonPath('meta.total', 48);

        $beyond = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets?limit=15&cursor=60');

        $beyond->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.total', 48);
    }

    /**
     * GWT: Given outlet user, When calling GET /admin/outlets, Then 403
     */
    public function test_non_admin_cannot_list_outlets(): void
    {
        $token = $this->loginAsOutletUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/outlets');

        $response->assertStatus(403);
    }

    // =====================================================================
    // 2) Admin Outlet Update
    // =====================================================================

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with name/category/address, Then updated and returned
     */
    public function test_admin_can_update_outlet(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create(['name' => 'Original Name']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", [
                'name' => 'Updated Name',
                'category' => 'warung',
                'address' => 'Jl. Baru No. 1',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.category', 'warung')
            ->assertJsonPath('data.address', 'Jl. Baru No. 1');
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with invalid category, Then 422
     */
    public function test_admin_cannot_update_outlet_with_invalid_category(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", [
                'category' => 'invalid_category',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with valid categories, Then accepted
     */
    public function test_admin_can_update_outlet_with_all_valid_categories(): void
    {
        $token = $this->loginAsAdmin();

        foreach (Outlet::VALID_CATEGORIES as $category) {
            $outlet = Outlet::factory()->create(['category' => 'lainnya']);

            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->patchJson("/api/admin/outlets/{$outlet->id}", [
                    'category' => $category,
                ]);

            $response->assertOk()
                ->assertJsonPath('data.category', $category);
        }
    }

    /**
     * GWT: Given outlet user, When PATCH /admin/outlets/{id}, Then 403
     */
    public function test_non_admin_cannot_update_outlet(): void
    {
        $token = $this->loginAsOutletUser();
        $outlet = Outlet::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", [
                'name' => 'Test',
                'category' => 'warung',
            ]);

        $response->assertStatus(403);
    }

    // =====================================================================
    // 3) Outlet Category Default + Enum Values
    // =====================================================================

    /**
     * GWT: Given new outlet with no category, When created, Then category='lainnya'
     */
    public function test_outlet_category_defaults_to_lainnya(): void
    {
        $outlet = Outlet::factory()->create();

        $this->assertEquals('lainnya', $outlet->category);
        $this->assertContains($outlet->category, Outlet::VALID_CATEGORIES);
    }

    /**
     * GWT: Given VALID_CATEGORIES constant, When checked, Then contains expected values
     */
    public function test_outlet_valid_categories(): void
    {
        $expected = ['warung', 'minimarket', 'supermarket', 'grosir', 'restoran', 'kafe', 'toko_kelontong', 'lainnya'];

        $this->assertEquals($expected, Outlet::VALID_CATEGORIES);
    }

    // =====================================================================
    // 4) Outlet Purchase History (Orders + Summary)
    // =====================================================================

    /**
     * GWT: Given admin, When calling GET /admin/outlets/{id}/orders, Then paginated order list with status, date, total
     */
    public function test_admin_can_view_outlet_orders(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create();

        Order::create([
            'order_id' => 'ORD-HIST-001',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 500000,
            'idempotency_key' => 'hist-001',
        ]);
        Order::create([
            'order_id' => 'ORD-HIST-002',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 300000,
            'idempotency_key' => 'hist-002',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/admin/outlets/{$outlet->id}/orders");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'order_id', 'status', 'total_amount', 'created_at'],
                ],
            ]);
    }

    /**
     * GWT: Given admin, When calling GET /admin/outlets/{id}/summary, Then total_orders, total_amount returned
     */
    public function test_admin_can_view_outlet_order_summary(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create();

        Order::create([
            'order_id' => 'ORD-SUM-001',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 500000,
            'idempotency_key' => 'sum-001',
        ]);
        Order::create([
            'order_id' => 'ORD-SUM-002',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 300000,
            'idempotency_key' => 'sum-002',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/admin/outlets/{$outlet->id}/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total_orders',
                    'total_amount',
                ],
            ])
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_amount', '800000.00');
    }

    /**
     * GWT: Given outlet user, When GET /admin/outlets/{id}/orders, Then 403
     */
    public function test_non_admin_cannot_view_outlet_orders(): void
    {
        $token = $this->loginAsOutletUser();
        $outlet = Outlet::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/admin/outlets/{$outlet->id}/orders");

        $response->assertStatus(403);
    }

    // =====================================================================
    // 5) Admin Outlet Create (POST /admin/outlets)
    // =====================================================================

    /**
     * GWT: Given admin, When POST /admin/outlets with valid payload,
     * Then 201 with the created outlet (territory loaded), category/territory persisted.
     */
    public function test_admin_can_create_outlet(): void
    {
        $token = $this->loginAsAdmin();
        $territory = Territory::create(['name' => 'Bekasi', 'code' => 'bekasi']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/outlets', [
                'name' => 'Warung Baru',
                'phone' => '081234567890',
                'address' => 'Jl. Merdeka No. 10',
                'city' => 'Bekasi',
                'district' => 'Bekasi Selatan',
                'category' => 'warung',
                'territory_id' => $territory->id,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Warung Baru')
            ->assertJsonPath('data.category', 'warung')
            ->assertJsonPath('data.territory_id', $territory->id)
            ->assertJsonPath('data.territory.id', $territory->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('outlets', [
            'name' => 'Warung Baru',
            'category' => 'warung',
            'territory_id' => $territory->id,
        ]);
    }

    /**
     * GWT: Given admin, When POST /admin/outlets with a duplicate phone,
     * Then 422 with a phone validation error (canonical uniqueness).
     */
    public function test_admin_cannot_create_outlet_with_duplicate_phone(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->create(['phone' => '081234567890']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/outlets', [
                'name' => 'Warung Duplikat',
                'phone' => '+62 812-3456-7890',
                'address' => 'Jl. Merdeka No. 11',
                'city' => 'Bekasi',
                'district' => 'Bekasi Selatan',
                'category' => 'warung',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * GWT: Given admin, When POST /admin/outlets with an invalid category, Then 422.
     */
    public function test_admin_cannot_create_outlet_with_invalid_category(): void
    {
        $token = $this->loginAsAdmin();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/outlets', [
                'name' => 'Warung Invalid',
                'phone' => '081298765432',
                'address' => 'Jl. Merdeka No. 12',
                'city' => 'Bekasi',
                'district' => 'Bekasi Selatan',
                'category' => 'invalid_category',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    /**
     * GWT: Given admin, When POST /admin/outlets with a non-existent territory_id,
     * Then 422 (never a raw FK 500).
     */
    public function test_admin_cannot_create_outlet_with_unknown_territory(): void
    {
        $token = $this->loginAsAdmin();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/outlets', [
                'name' => 'Warung Tanpa Wilayah',
                'phone' => '081311223344',
                'address' => 'Jl. Merdeka No. 13',
                'city' => 'Bekasi',
                'district' => 'Bekasi Selatan',
                'category' => 'warung',
                'territory_id' => 999999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['territory_id']);
    }

    /**
     * GWT: Given admin, When POST /admin/outlets without required fields, Then 422.
     */
    public function test_admin_cannot_create_outlet_without_required_fields(): void
    {
        $token = $this->loginAsAdmin();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/outlets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone', 'address', 'city', 'district', 'category']);
    }

    /**
     * GWT: Given a client-supplied user_id, When POST /admin/outlets,
     * Then 422 (ownership is server-assigned; client may not inject it).
     */
    public function test_admin_create_outlet_rejects_client_supplied_user_id(): void
    {
        $token = $this->loginAsAdmin();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/outlets', [
                'name' => 'Warung Injected',
                'phone' => '081355667788',
                'address' => 'Jl. Merdeka No. 14',
                'city' => 'Bekasi',
                'district' => 'Bekasi Selatan',
                'category' => 'warung',
                'user_id' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /**
     * GWT: Given outlet user, When POST /admin/outlets, Then 403.
     */
    public function test_non_admin_cannot_create_outlet(): void
    {
        $token = $this->loginAsOutletUser();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/outlets', [
                'name' => 'Warung Nakal',
                'phone' => '081399887766',
                'address' => 'Jl. Merdeka No. 15',
                'city' => 'Bekasi',
                'district' => 'Bekasi Selatan',
                'category' => 'warung',
            ]);

        $response->assertStatus(403);
    }

    // =====================================================================
    // 6) Outlet Deactivate via PATCH + phone/territory edit
    // =====================================================================

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with is_active=false,
     * Then the outlet is deactivated (soft) and its orders are preserved.
     */
    public function test_admin_can_deactivate_outlet_and_orders_are_preserved(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create(['is_active' => true]);

        Order::create([
            'order_id' => 'ORD-DEACT-001',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 150000,
            'idempotency_key' => 'deact-001',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", ['is_active' => false]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('outlets', ['id' => $outlet->id, 'is_active' => false]);
        $this->assertDatabaseHas('orders', ['outlet_id' => $outlet->id, 'order_id' => 'ORD-DEACT-001']);
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} WITHOUT a phone key,
     * Then 200 (the canonical-phone guard must not fire on phone-less updates).
     */
    public function test_admin_can_update_outlet_without_phone_key(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create(['name' => 'Original Name']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", ['name' => 'Renamed Without Phone']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Renamed Without Phone');
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with a new unique phone,
     * Then the phone (and its canonical form) is updated.
     */
    public function test_admin_can_update_outlet_phone(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create(['phone' => '081200000001']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", ['phone' => '081200000099']);

        $response->assertOk()
            ->assertJsonPath('data.phone', '081200000099');

        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'phone' => '081200000099',
            'canonical_phone' => '+6281200000099',
        ]);
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with an empty-string phone,
     * Then 422 (not a 500 from the model's phone mutator).
     */
    public function test_admin_cannot_update_outlet_with_empty_phone(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", ['phone' => '']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with another outlet's phone,
     * Then 422 (uniqueness excludes self).
     */
    public function test_admin_cannot_update_outlet_to_duplicate_phone(): void
    {
        $token = $this->loginAsAdmin();
        Outlet::factory()->create(['phone' => '081200000002']);
        $outlet = Outlet::factory()->create(['phone' => '081200000003']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", ['phone' => '081200000002']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} keeping its own phone,
     * Then 200 (uniqueness must not reject the row's current phone).
     */
    public function test_admin_can_keep_own_phone_on_update(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create(['phone' => '081200000004']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", ['phone' => '081200000004']);

        $response->assertOk()
            ->assertJsonPath('data.phone', '081200000004');
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with a valid territory_id,
     * Then the territory is persisted and returned loaded.
     */
    public function test_admin_can_update_outlet_territory(): void
    {
        $token = $this->loginAsAdmin();
        $territory = Territory::create(['name' => 'Depok', 'code' => 'depok']);
        $outlet = Outlet::factory()->create(['territory_id' => null]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", ['territory_id' => $territory->id]);

        $response->assertOk()
            ->assertJsonPath('data.territory_id', $territory->id)
            ->assertJsonPath('data.territory.id', $territory->id);
    }

    /**
     * GWT: Given admin, When PATCH /admin/outlets/{id} with an unknown territory_id,
     * Then 422.
     */
    public function test_admin_cannot_update_outlet_with_unknown_territory(): void
    {
        $token = $this->loginAsAdmin();
        $outlet = Outlet::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/outlets/{$outlet->id}", ['territory_id' => 999999]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['territory_id']);
    }
}
