<?php

namespace Tests\Feature;

use App\Models\MenuDefinition;
use App\Models\RoleMenuAccess;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\RbacMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8 RBAC catalog additions: `field_ops` + `driver_roster`.
 *
 * Locks the 19 -> 21 menu expansion and the default access levels added for
 * the mobile field operations surfaces.
 */
class RbacFieldOpsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_contains_field_ops_and_driver_roster(): void
    {
        $keys = MenuDefinition::keys();

        $this->assertContains('field_ops', $keys);
        $this->assertContains('driver_roster', $keys);
        $this->assertCount(21, $keys);
    }

    public function test_catalog_metadata_continues_sort_order_and_grouping(): void
    {
        $catalog = collect(MenuDefinition::CATALOG)->keyBy('key');

        $this->assertSame('Operasi Lapangan', $catalog['field_ops']['label']);
        $this->assertSame('operasional', $catalog['field_ops']['group']);
        $this->assertSame(20, $catalog['field_ops']['sort']);

        $this->assertSame('Roster Driver', $catalog['driver_roster']['label']);
        $this->assertSame('admin', $catalog['driver_roster']['group']);
        $this->assertSame(21, $catalog['driver_roster']['sort']);
    }

    public function test_seeder_creates_twenty_one_menus_and_eighty_three_cells(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $this->assertSame(21, MenuDefinition::count());
        $this->assertSame(83, RoleMenuAccess::where('level', '!=', 'none')->count());
    }

    public function test_default_access_levels_for_new_menus(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        // field_ops: owner/admin edit, sales/driver read.
        $this->assertSame('edit', $this->level('platform_owner', 'field_ops'));
        $this->assertSame('edit', $this->level('admin', 'field_ops'));
        $this->assertSame('read', $this->level('sales', 'field_ops'));
        $this->assertSame('read', $this->level('driver', 'field_ops'));
        $this->assertSame('none', $this->level('finance', 'field_ops'));
        $this->assertSame('none', $this->level('outlet', 'field_ops'));

        // driver_roster: owner/admin edit only.
        $this->assertSame('edit', $this->level('platform_owner', 'driver_roster'));
        $this->assertSame('edit', $this->level('admin', 'driver_roster'));
        $this->assertSame('none', $this->level('sales', 'driver_roster'));
        $this->assertSame('none', $this->level('driver', 'driver_roster'));
        $this->assertSame('none', $this->level('finance', 'driver_roster'));
    }

    public function test_matrix_endpoint_exposes_twenty_one_keys_per_role(): void
    {
        $admin = User::factory()->admin()->create();
        $token = 'Bearer ' . app(AuthService::class)->createToken($admin)['token'];

        $response = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/rbac/matrix')
            ->assertOk();

        foreach ($response->json('data') as $role => $map) {
            $this->assertCount(21, $map, "role {$role} should expose 21 menu keys");
        }

        $this->assertSame('read', $response->json('data.driver.field_ops'));
        $this->assertSame('none', $response->json('data.driver.driver_roster'));
    }

    public function test_seeder_is_idempotent_with_new_menus(): void
    {
        $this->seed(RbacMatrixSeeder::class);
        $menus = MenuDefinition::count();
        $cells = RoleMenuAccess::count();

        $this->seed(RbacMatrixSeeder::class);

        $this->assertSame($menus, MenuDefinition::count());
        $this->assertSame($cells, RoleMenuAccess::count());
    }

    private function level(string $role, string $menuKey): string
    {
        return RoleMenuAccess::where('role', $role)
            ->where('menu_key', $menuKey)
            ->value('level') ?? 'none';
    }
}
