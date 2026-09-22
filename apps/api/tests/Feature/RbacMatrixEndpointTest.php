<?php

namespace Tests\Feature;

use App\Models\MenuDefinition;
use App\Models\RoleMenuAccess;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET/PUT /admin/rbac/matrix (K-A: controller-level assertAdminOrOwner,
 * NOT rbac: middleware).
 */
class RbacMatrixEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return 'Bearer ' . app(AuthService::class)->createToken($user)['token'];
    }

    private function putMatrix(User $user, array $cells)
    {
        return $this->withHeader('Authorization', $this->tokenFor($user))
            ->putJson('/api/admin/rbac/matrix', ['cells' => $cells]);
    }

    // ---------------------------------------------------------------- GET

    public function test_admin_can_view_full_matrix(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->withHeader('Authorization', $this->tokenFor($admin))
            ->getJson('/api/admin/rbac/matrix')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertCount(7, $data);

        foreach ($data as $role => $map) {
            $this->assertCount(21, $map, "role {$role} should expose 21 menu keys");
        }

        $this->assertSame('read', $data['admin']['rbac_matrix']);
        $this->assertSame('edit', $data['admin']['products']);
        $this->assertSame('none', $data['finance']['analytics']);
    }

    public function test_platform_owner_can_view_matrix(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->withHeader('Authorization', $this->tokenFor($owner))
            ->getJson('/api/admin/rbac/matrix')
            ->assertOk();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $sales = User::factory()->sales()->create();

        $this->withHeader('Authorization', $this->tokenFor($sales))
            ->getJson('/api/admin/rbac/matrix')
            ->assertForbidden();
    }

    public function test_unauthenticated_is_401(): void
    {
        $this->getJson('/api/admin/rbac/matrix')->assertUnauthorized();
    }

    public function test_empty_database_returns_all_none_without_error(): void
    {
        // Simulate "seed not run": no menu definitions, no matrix rows.
        RoleMenuAccess::query()->delete();
        MenuDefinition::query()->delete();

        $admin = User::factory()->admin()->create();

        $response = $this->withHeader('Authorization', $this->tokenFor($admin))
            ->getJson('/api/admin/rbac/matrix')
            ->assertOk();

        $data = $response->json('data');
        $this->assertCount(7, $data);
        foreach ($data as $map) {
            $this->assertCount(21, $map);
            $this->assertSame(['none'], array_values(array_unique($map)));
        }
    }

    // ---------------------------------------------------------------- PUT happy

    public function test_admin_can_update_a_cell_and_response_reflects_it(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->putMatrix($admin, [
            ['role' => 'sales', 'menu_key' => 'products', 'level' => 'edit'],
        ])->assertOk()
          ->assertJsonPath('data.sales.products', 'edit');

        $this->assertSame('edit', $response->json('data.sales.products'));
        $this->assertDatabaseHas('role_menu_access', [
            'role' => 'sales',
            'menu_key' => 'products',
            'level' => 'edit',
        ]);
    }

    public function test_setting_level_none_removes_the_row(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertDatabaseHas('role_menu_access', ['role' => 'sales', 'menu_key' => 'products']);

        $this->putMatrix($admin, [
            ['role' => 'sales', 'menu_key' => 'products', 'level' => 'none'],
        ])->assertOk()
          ->assertJsonPath('data.sales.products', 'none');

        $this->assertDatabaseMissing('role_menu_access', ['role' => 'sales', 'menu_key' => 'products']);
    }

    // ---------------------------------------------------------------- PUT rules

    public function test_admin_cannot_modify_platform_owner_row(): void
    {
        $admin = User::factory()->admin()->create();

        $this->putMatrix($admin, [
            ['role' => 'platform_owner', 'menu_key' => 'dashboard', 'level' => 'read'],
        ])->assertForbidden();

        // Untouched.
        $this->assertSame(
            'edit',
            RoleMenuAccess::where('role', 'platform_owner')->where('menu_key', 'dashboard')->value('level'),
        );
    }

    public function test_mixed_valid_and_forbidden_is_all_or_nothing(): void
    {
        $admin = User::factory()->admin()->create();

        $before = RoleMenuAccess::where('role', 'sales')->where('menu_key', 'products')->value('level');

        $this->putMatrix($admin, [
            ['role' => 'sales', 'menu_key' => 'products', 'level' => 'edit'],
            ['role' => 'sales', 'menu_key' => 'orders', 'level' => 'read'],
            ['role' => 'driver', 'menu_key' => 'delivery', 'level' => 'edit'],
            ['role' => 'outlet', 'menu_key' => 'dashboard', 'level' => 'edit'],
            ['role' => 'finance', 'menu_key' => 'invoices', 'level' => 'edit'],
            ['role' => 'platform_owner', 'menu_key' => 'analytics', 'level' => 'none'],
        ])->assertForbidden();

        // None of the 5 valid cells were applied.
        $this->assertSame(
            $before,
            RoleMenuAccess::where('role', 'sales')->where('menu_key', 'products')->value('level'),
        );
    }

    public function test_admin_self_lockout_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->putMatrix($admin, [
            ['role' => 'admin', 'menu_key' => 'rbac_matrix', 'level' => 'none'],
        ])->assertStatus(422);

        $this->assertSame(
            'read',
            RoleMenuAccess::where('role', 'admin')->where('menu_key', 'rbac_matrix')->value('level'),
        );
    }

    public function test_owner_self_lockout_is_rejected(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->putMatrix($owner, [
            ['role' => 'platform_owner', 'menu_key' => 'rbac_matrix', 'level' => 'none'],
        ])->assertStatus(422);
    }

    public function test_owner_can_downgrade_admin(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->putMatrix($owner, [
            ['role' => 'admin', 'menu_key' => 'rbac_matrix', 'level' => 'none'],
        ])->assertOk()
          ->assertJsonPath('data.admin.rbac_matrix', 'none');
    }

    public function test_invalid_menu_key_is_rejected_all_or_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $before = RoleMenuAccess::where('role', 'sales')->where('menu_key', 'products')->value('level');

        $this->putMatrix($admin, [
            ['role' => 'sales', 'menu_key' => 'products', 'level' => 'edit'],
            ['role' => 'sales', 'menu_key' => 'nonexistent_menu', 'level' => 'read'],
        ])->assertStatus(422);

        $this->assertSame(
            $before,
            RoleMenuAccess::where('role', 'sales')->where('menu_key', 'products')->value('level'),
        );
    }

    public function test_invalid_level_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->putMatrix($admin, [
            ['role' => 'sales', 'menu_key' => 'products', 'level' => 'write'],
        ])->assertStatus(422);
    }

    public function test_empty_cells_array_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->putMatrix($admin, [])->assertStatus(422);
    }
}
