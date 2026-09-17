<?php

namespace Tests\Feature;

use App\Models\RoleAssignmentAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────

    private function loginAs(User $user, string $password = 'password'): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk()->json('data.token');
    }

    private function createUserWithRole(string $role, string $emailPrefix, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'email' => "{$emailPrefix}@example.test",
            'password' => Hash::make('password'),
        ], $attributes));
    }

    // ─── F1: Platform Owner Superset Access ──────────────────────────

    public function test_platform_owner_can_access_admin_endpoints(): void
    {
        $owner = $this->createUserWithRole('platform_owner', 'po-admin-list');
        $token = $this->loginAs($owner);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertOk();
    }

    public function test_admin_cannot_access_platform_owner_only_endpoints(): void
    {
        $admin = $this->createUserWithRole('admin', 'admin-owner-only');
        $target = $this->createUserWithRole('outlet', 'target-owner-only');
        $token = $this->loginAs($admin);

        // Role management (assign any role) is platform_owner-only
        $this->withToken($token)
            ->patchJson("/api/admin/users/{$target->id}/role", ['role' => 'sales'])
            ->assertForbidden();
    }

    public function test_outlet_cannot_access_admin_endpoints(): void
    {
        $outlet = $this->createUserWithRole('outlet', 'outlet-admin-list');
        $token = $this->loginAs($outlet);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_supplier_cannot_access_admin_endpoints(): void
    {
        $supplier = $this->createUserWithRole('supplier', 'supplier-admin-list');
        $token = $this->loginAs($supplier);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_sales_cannot_access_admin_endpoints(): void
    {
        $sales = $this->createUserWithRole('sales', 'sales-admin-list');
        $token = $this->loginAs($sales);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_driver_cannot_access_admin_endpoints(): void
    {
        $driver = $this->createUserWithRole('driver', 'driver-admin-list');
        $token = $this->loginAs($driver);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_finance_cannot_access_admin_endpoints(): void
    {
        $finance = $this->createUserWithRole('finance', 'finance-admin-list');
        $token = $this->loginAs($finance);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    // ─── F1: Role Assignment with Audit + JWT Invalidation ───────────

    public function test_platform_owner_can_assign_role_with_audit(): void
    {
        $owner = $this->createUserWithRole('platform_owner', 'po-assign-role');
        $target = $this->createUserWithRole('sales', 'target-assign-role');
        $token = $this->loginAs($owner);

        $this->withToken($token)
            ->patchJson("/api/admin/users/{$target->id}/role", ['role' => 'driver'])
            ->assertOk()
            ->assertJsonPath('data.role', 'driver');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'driver']);
        $this->assertDatabaseHas('role_assignment_audits', [
            'target_user_id' => $target->id,
            'actor_user_id' => $owner->id,
            'from_role' => 'sales',
            'to_role' => 'driver',
            'action' => RoleAssignmentAudit::ASSIGNED,
        ]);
    }

    public function test_role_assignment_invalidates_jwt(): void
    {
        $owner = $this->createUserWithRole('platform_owner', 'po-jwt-inv');
        $target = $this->createUserWithRole('outlet', 'target-jwt-inv');
        $targetToken = $this->loginAs($target);

        // Platform owner changes target's role
        $ownerToken = $this->loginAs($owner);
        $this->withToken($ownerToken)
            ->patchJson("/api/admin/users/{$target->id}/role", ['role' => 'sales'])
            ->assertOk();

        // Target's old token should now be invalid — stale iat < updated_at
        // causes the RejectStaleJwt middleware to return 401.
        $this->withToken($targetToken)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_invalid_role_rejected(): void
    {
        $owner = $this->createUserWithRole('platform_owner', 'po-invalid-role');
        $target = $this->createUserWithRole('outlet', 'target-invalid-role');
        $token = $this->loginAs($owner);

        $this->withToken($token)
            ->patchJson("/api/admin/users/{$target->id}/role", ['role' => 'superuser'])
            ->assertStatus(422);
    }

    // ─── F1: User Profile Update ─────────────────────────────────────

    public function test_user_can_update_profile_name_and_email(): void
    {
        $user = $this->createUserWithRole('outlet', 'profile-update');
        $token = $this->loginAs($user);

        $this->withToken($token)
            ->patchJson('/api/auth/me', [
                'name' => 'Updated Name',
                'email' => 'updated-profile@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'updated-profile@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated-profile@example.test',
        ]);
    }

    public function test_profile_update_ignores_role_field(): void
    {
        $user = $this->createUserWithRole('outlet', 'profile-role-ignored');
        $token = $this->loginAs($user);

        $this->withToken($token)
            ->patchJson('/api/auth/me', [
                'name' => 'Still Outlet',
                'role' => 'admin', // should be ignored
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'outlet');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'outlet']);
    }

    // ─── F1: User Listing with Pagination + Filter ───────────────────

    public function test_admin_can_list_users_with_role_filter(): void
    {
        $admin = $this->createUserWithRole('admin', 'admin-list-filter');
        $this->createUserWithRole('sales', 'sales-list-1');
        $this->createUserWithRole('sales', 'sales-list-2');
        $this->createUserWithRole('outlet', 'outlet-list-1');

        $token = $this->loginAs($admin);

        $response = $this->withToken($token)
            ->getJson('/api/admin/users?role=sales&limit=10')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $user) {
            $this->assertEquals('sales', $user['role']);
        }
    }

    public function test_user_listing_has_pagination_meta(): void
    {
        $admin = $this->createUserWithRole('admin', 'admin-list-pagination');
        $this->createUserWithRole('outlet', 'extra-pagination'); // ensure > 1 user
        $token = $this->loginAs($admin);

        $response = $this->withToken($token)
            ->getJson('/api/admin/users?limit=1')
            ->assertOk();

        $this->assertArrayHasKey('meta', $response->json());
        $meta = $response->json('meta');
        $this->assertArrayHasKey('limit', $meta);
        $this->assertArrayHasKey('has_more', $meta);
        $this->assertEquals(1, $meta['limit']);
        $this->assertTrue($meta['has_more']); // at least admin + other users
    }

    public function test_user_listing_limit_plus_one_technique(): void
    {
        $admin = $this->createUserWithRole('admin', 'admin-lp1');
        $this->createUserWithRole('outlet', 'outlet-lp1-1');
        $this->createUserWithRole('outlet', 'outlet-lp1-2');
        $this->createUserWithRole('sales', 'sales-lp1');

        $token = $this->loginAs($admin);

        // Request limit=2: query fetches 3 (limit+1), returns 2, has_more=true
        $response = $this->withToken($token)
            ->getJson('/api/admin/users?limit=2')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertTrue($response->json('meta.has_more'));
    }

    /**
     * GWT: Given users with varied created_at, When GET /api/admin/users?limit=20
     * (auth admin), Then data[0] is the newest created_at (id DESC tiebreak),
     * meta.total = total users, meta.cursor = 0.
     */
    public function test_user_listing_defaults_to_newest_first_with_total_and_cursor(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $admin = $this->createUserWithRole('admin', 'admin-newest-default', [
            'created_at' => '2026-01-01 08:00:00',
        ]);
        $oldest = $this->createUserWithRole('outlet', 'user-newest-oldest', [
            'created_at' => '2026-01-02 08:00:00',
        ]);
        $newest = $this->createUserWithRole('sales', 'user-newest-latest', [
            'created_at' => '2026-03-01 08:00:00',
        ]);

        $token = $this->loginAs($admin);

        $this->withToken($token)
            ->getJson('/api/admin/users?limit=20')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.cursor', 0)
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $oldest->id)
            ->assertJsonPath('data.2.id', $admin->id);
    }

    /**
     * GWT: Given an invalid sort param, When GET /api/admin/users, Then HTTP 200
     * with the default newest-first order (never 422/500).
     */
    public function test_user_listing_silently_falls_back_for_invalid_sort(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $admin = $this->createUserWithRole('admin', 'admin-invalid-sort', ['created_at' => '2026-01-01 08:00:00']);
        $this->createUserWithRole('outlet', 'user-invalid-older', ['created_at' => '2026-01-01 08:00:00']);
        $newer = $this->createUserWithRole('sales', 'user-invalid-newer', ['created_at' => '2026-03-01 08:00:00']);

        $token = $this->loginAs($admin);

        $this->withToken($token)
            ->getJson('/api/admin/users?sort=__proto__&order=desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id);
    }

    /**
     * GWT: Given a non-scalar (array) sort param, When GET /api/admin/users,
     * Then HTTP 200 with the default newest-first order (never 500).
     */
    public function test_user_listing_handles_non_scalar_sort_param_without_error(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        $admin = $this->createUserWithRole('admin', 'admin-array-sort', ['created_at' => '2026-01-01 08:00:00']);
        $this->createUserWithRole('outlet', 'user-array-older', ['created_at' => '2026-01-01 08:00:00']);
        $newer = $this->createUserWithRole('sales', 'user-array-newer', ['created_at' => '2026-03-01 08:00:00']);

        $token = $this->loginAs($admin);

        $this->withToken($token)
            ->getJson('/api/admin/users?sort[]=x')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id);
    }

    /**
     * GWT: Given sort=name&order=asc, When GET /api/admin/users, Then users are
     * ordered by name ascending.
     */
    public function test_user_listing_sorts_by_name_ascending(): void
    {
        $admin = $this->createUserWithRole('admin', 'admin-name-sort', ['name' => 'Zulu Admin']);
        $this->createUserWithRole('outlet', 'user-name-zebra', ['name' => 'Zebra User']);
        $this->createUserWithRole('sales', 'user-name-alpha', ['name' => 'Alpha User']);
        $this->createUserWithRole('sales', 'user-name-mango', ['name' => 'Mango User']);

        $token = $this->loginAs($admin);

        $this->withToken($token)
            ->getJson('/api/admin/users?sort=name&order=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha User')
            ->assertJsonPath('data.1.name', 'Mango User')
            ->assertJsonPath('data.2.name', 'Zebra User')
            ->assertJsonPath('data.3.name', 'Zulu Admin');
    }

    /**
     * GWT: Given role=sales, When GET /api/admin/users?role=sales&limit=20,
     * Then meta.total counts sales ONLY (filtered) and every row is sales.
     */
    public function test_user_listing_role_filtered_total_counts_only_matching_role(): void
    {
        $admin = $this->createUserWithRole('admin', 'admin-filtered-total');
        $this->createUserWithRole('outlet', 'user-filtered-outlet');
        $this->createUserWithRole('sales', 'user-filtered-sales-1');
        $this->createUserWithRole('sales', 'user-filtered-sales-2');
        $this->createUserWithRole('sales', 'user-filtered-sales-3');

        $token = $this->loginAs($admin);

        $response = $this->withToken($token)
            ->getJson('/api/admin/users?role=sales&limit=20')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $data = $response->json('data');
        $this->assertCount(3, $data);

        foreach ($data as $user) {
            $this->assertEquals('sales', $user['role']);
        }
    }

    // ─── F1: Fix updatePaymentTerms missing admin assertion ──────────

    public function test_update_payment_terms_requires_admin(): void
    {
        $outletUser = $this->createUserWithRole('outlet', 'outlet-pay-terms');
        $outlet = \App\Models\Outlet::factory()->create([
            'user_id' => $outletUser->id,
            'is_active' => true,
        ]);
        $token = $this->loginAs($outletUser);

        $this->withToken($token)
            ->postJson("/api/admin/outlets/{$outlet->id}/payment-terms", [
                'payment_term_days' => 30,
            ])
            ->assertForbidden();
    }

    public function test_update_payment_terms_platform_owner_allowed(): void
    {
        $owner = $this->createUserWithRole('platform_owner', 'po-pay-terms');
        $outletUser = $this->createUserWithRole('outlet', 'outlet-pay-terms-owner');
        $outlet = \App\Models\Outlet::factory()->create([
            'user_id' => $outletUser->id,
            'is_active' => true,
        ]);
        $token = $this->loginAs($owner);

        $this->withToken($token)
            ->postJson("/api/admin/outlets/{$outlet->id}/payment-terms", [
                'payment_term_days' => 45,
            ])
            ->assertOk();
    }
}
