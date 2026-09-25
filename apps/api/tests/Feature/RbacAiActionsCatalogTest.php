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
 * Phase 9 RBAC catalog additions: `ai_actions` + `supply_chain`.
 *
 * Locks the 21 -> 23 menu expansion and the default access levels added for
 * the AI actions and supply chain surfaces.
 */
class RbacAiActionsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_contains_ai_actions_and_supply_chain(): void
    {
        $keys = MenuDefinition::keys();

        $this->assertContains('ai_actions', $keys);
        $this->assertContains('supply_chain', $keys);
        $this->assertCount(23, $keys);
    }

    public function test_catalog_metadata_continues_sort_order_and_grouping(): void
    {
        $catalog = collect(MenuDefinition::CATALOG)->keyBy('key');

        // ai_actions: label "Aksi AI", group "analitik", sort 22
        $this->assertSame('Aksi AI', $catalog['ai_actions']['label']);
        $this->assertSame('analitik', $catalog['ai_actions']['group']);
        $this->assertSame(22, $catalog['ai_actions']['sort']);

        // supply_chain: label "Rantai Pasok", group "operasional", sort 23
        $this->assertSame('Rantai Pasok', $catalog['supply_chain']['label']);
        $this->assertSame('operasional', $catalog['supply_chain']['group']);
        $this->assertSame(23, $catalog['supply_chain']['sort']);
    }

    public function test_seeder_creates_twenty_three_menus_and_eighty_seven_cells(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        $this->assertSame(23, MenuDefinition::count());
        $this->assertSame(87, RoleMenuAccess::where('level', '!=', 'none')->count());
    }

    public function test_default_access_levels_for_new_menus(): void
    {
        $this->seed(RbacMatrixSeeder::class);

        // ai_actions: platform_owner/admin edit, others none.
        $this->assertSame('edit', $this->level('platform_owner', 'ai_actions'));
        $this->assertSame('edit', $this->level('admin', 'ai_actions'));
        $this->assertSame('none', $this->level('outlet', 'ai_actions'));
        $this->assertSame('none', $this->level('supplier', 'ai_actions'));
        $this->assertSame('none', $this->level('sales', 'ai_actions'));
        $this->assertSame('none', $this->level('driver', 'ai_actions'));
        $this->assertSame('none', $this->level('finance', 'ai_actions'));

        // supply_chain: platform_owner/admin edit, others none.
        $this->assertSame('edit', $this->level('platform_owner', 'supply_chain'));
        $this->assertSame('edit', $this->level('admin', 'supply_chain'));
        $this->assertSame('none', $this->level('outlet', 'supply_chain'));
        $this->assertSame('none', $this->level('supplier', 'supply_chain'));
        $this->assertSame('none', $this->level('sales', 'supply_chain'));
        $this->assertSame('none', $this->level('driver', 'supply_chain'));
        $this->assertSame('none', $this->level('finance', 'supply_chain'));

        $this->assertSame(
            0,
            RoleMenuAccess::whereIn('role', ['outlet', 'supplier', 'sales', 'driver', 'finance'])
                ->whereIn('menu_key', ['ai_actions', 'supply_chain'])
                ->count(),
            'non-owner/admin roles must have no persisted rows for the new menus',
        );
    }

    public function test_matrix_endpoint_exposes_twenty_three_keys_per_role(): void
    {
        $admin = User::factory()->admin()->create();
        $token = 'Bearer ' . app(AuthService::class)->createToken($admin)['token'];

        $response = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/rbac/matrix')
            ->assertOk();

        foreach ($response->json('data') as $role => $map) {
            $this->assertCount(23, $map, "role {$role} should expose 23 menu keys");
        }

        $this->assertSame('edit', $response->json('data.platform_owner.ai_actions'));
        $this->assertSame('edit', $response->json('data.admin.ai_actions'));
        $this->assertSame('none', $response->json('data.driver.ai_actions'));
        $this->assertSame('none', $response->json('data.finance.ai_actions'));

        $this->assertSame('edit', $response->json('data.platform_owner.supply_chain'));
        $this->assertSame('edit', $response->json('data.admin.supply_chain'));
        $this->assertSame('none', $response->json('data.driver.supply_chain'));
        $this->assertSame('none', $response->json('data.finance.supply_chain'));
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