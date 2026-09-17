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
                    'data' => [
                        '*' => ['id', 'name', 'phone', 'category', 'score'],
                    ],
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
            ->assertJsonCount(1, 'data.data');
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
            ->assertJsonCount(1, 'data.data');
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
            ->assertJsonCount(1, 'data.data');
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
            ->assertJsonCount(1, 'data.data');
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
            ->assertJsonCount(10, 'data.data')
            ->assertJsonPath('data.has_more', true);
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
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.has_more', false);
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
            ->assertJsonPath('data.data.0.name', 'Newest Outlet')
            ->assertJsonPath('data.data.1.name', 'Middle Outlet')
            ->assertJsonPath('data.data.2.name', 'Oldest Outlet');
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
}
