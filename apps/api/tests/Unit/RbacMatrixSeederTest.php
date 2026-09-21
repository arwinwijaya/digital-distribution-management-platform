<?php

namespace Tests\Unit;

use App\Models\MenuDefinition;
use App\Models\RoleMenuAccess;
use Database\Seeders\RbacMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RBAC default matrix seeder.
 *
 * Locks the seed against the spec default matrix (18 menus) plus the
 * `worktree` menu added during planning (19 menus, 77 non-none cells).
 */
class RbacMatrixSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Roles in the fixed order used by the spec table. */
    private const ROLES = ['platform_owner', 'admin', 'outlet', 'supplier', 'sales', 'driver', 'finance'];

    public function test_seeder_creates_nineteen_menu_definitions(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $this->assertSame(19, MenuDefinition::count());
    }

    public function test_seeder_creates_seventy_seven_non_none_cells(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $this->assertSame(
            77,
            RoleMenuAccess::where('level', '!=', 'none')->count(),
        );
    }

    public function test_every_role_menu_access_references_a_known_menu(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $known = MenuDefinition::pluck('key')->all();
        $referenced = RoleMenuAccess::pluck('menu_key')->unique()->all();

        foreach ($referenced as $menuKey) {
            $this->assertContains($menuKey, $known, "Unknown menu_key: {$menuKey}");
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RbacMatrixSeeder::class);
        $menuCount = MenuDefinition::count();
        $accessCount = RoleMenuAccess::count();

        $this->seed(RbacMatrixSeeder::class);

        $this->assertSame($menuCount, MenuDefinition::count());
        $this->assertSame($accessCount, RoleMenuAccess::count());
    }

    public function test_all_levels_are_within_the_allowed_set(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $levels = RoleMenuAccess::pluck('level')->unique()->sort()->values()->all();

        foreach ($levels as $level) {
            $this->assertContains($level, ['none', 'read', 'edit'], "Invalid level: {$level}");
        }
    }

    public function test_platform_owner_has_edit_on_every_non_worktree_menu(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $ownerLevels = RoleMenuAccess::where('role', 'platform_owner')
            ->where('menu_key', '!=', 'worktree')
            ->pluck('level', 'menu_key');

        $this->assertCount(18, $ownerLevels);

        foreach ($ownerLevels as $menuKey => $level) {
            $this->assertSame('edit', $level, "platform_owner should be edit on {$menuKey}");
        }
    }

    public function test_worktree_is_read_for_all_seven_roles(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $worktree = RoleMenuAccess::where('menu_key', 'worktree')->pluck('level', 'role');

        $this->assertCount(7, $worktree);

        foreach (self::ROLES as $role) {
            $this->assertSame('read', $worktree[$role] ?? null, "worktree should be read for {$role}");
        }
    }

    public function test_spec_sensitive_cells_are_not_widened(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        // Spec keeps these at read for admin (K-A / K-B in the plan rely on it).
        $this->assertSame('read', $this->level('admin', 'rbac_matrix'));
        $this->assertSame('read', $this->level('admin', 'admin_users'));
        $this->assertSame('read', $this->level('admin', 'analytics'));

        // Finance is read-only on the operations menus.
        $this->assertSame('read', $this->level('finance', 'products'));
        $this->assertSame('edit', $this->level('finance', 'payments'));
        $this->assertSame('edit', $this->level('finance', 'invoices'));
    }

    public function test_menus_not_granted_are_absent_or_none_for_restricted_roles(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        // finance must not reach the analytics / admin menus.
        foreach (['analytics', 'data_intelligence', 'operations', 'admin_orders', 'admin_users', 'rbac_matrix'] as $menuKey) {
            $level = $this->level('finance', $menuKey);
            $this->assertSame('none', $level, "finance should not have access to {$menuKey}");
        }
    }

    private function level(string $role, string $menuKey): string
    {
        return RoleMenuAccess::where('role', $role)
            ->where('menu_key', $menuKey)
            ->value('level') ?? 'none';
    }
}
