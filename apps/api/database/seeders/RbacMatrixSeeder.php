<?php

namespace Database\Seeders;

use App\Models\MenuDefinition;
use App\Models\RoleMenuAccess;
use Illuminate\Database\Seeder;

/**
 * Seeds the RBAC menu definitions and the default role x menu matrix.
 *
 * Source of truth: docs/pocket/spec/2026-09-18-rbac-menu-matrix/rbac-menu-matrix.md
 * ("Default Matrix (Seed)"), plus the `worktree` menu added during planning
 * (spec listed 18 menus; the app had 19), plus the Phase 8 field-operations
 * menus `field_ops` + `driver_roster`, plus the Phase 9 `ai_actions` +
 * `supply_chain` menus (23 menus x 7 roles = 87 non-none cells).
 *
 * Only non-`none` cells are persisted; a missing row is interpreted as `none`
 * by the Rbac middleware and by RbacMatrixService. Fully idempotent via
 * updateOrCreate, so `php artisan db:seed` can be re-run safely.
 */
class RbacMatrixSeeder extends Seeder
{
    /**
     * Default matrix. Only non-`none` cells are listed; anything omitted is
     * `none` for that role. Column order matches the spec table.
     *
     * @var array<string, array<string, string>>
     */
    private const MATRIX = [
        'dashboard' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'edit',
            'supplier' => 'edit', 'sales' => 'edit', 'driver' => 'edit', 'finance' => 'read',
        ],
        'orders' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'edit',
            'supplier' => 'read', 'sales' => 'edit', 'driver' => 'read', 'finance' => 'read',
        ],
        'products' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'read',
            'supplier' => 'read', 'sales' => 'read', 'driver' => 'read', 'finance' => 'read',
        ],
        'outlets' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'read',
            'sales' => 'read', 'finance' => 'read',
        ],
        'marketplace' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'edit',
            'supplier' => 'edit', 'sales' => 'edit', 'finance' => 'read',
        ],
        'payments' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'edit',
            'sales' => 'read', 'finance' => 'edit',
        ],
        'delivery' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'read',
            'sales' => 'read', 'driver' => 'edit', 'finance' => 'read',
        ],
        'sales' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'read',
            'sales' => 'edit', 'finance' => 'read',
        ],
        'invoices' => [
            'platform_owner' => 'edit', 'admin' => 'edit', 'outlet' => 'read',
            'finance' => 'edit',
        ],
        'worktree' => [
            'platform_owner' => 'read', 'admin' => 'read', 'outlet' => 'read',
            'supplier' => 'read', 'sales' => 'read', 'driver' => 'read', 'finance' => 'read',
        ],
        'analytics' => [
            'platform_owner' => 'edit', 'admin' => 'read',
        ],
        'data_intelligence' => [
            'platform_owner' => 'edit', 'admin' => 'read',
        ],
        'operations' => [
            'platform_owner' => 'edit', 'admin' => 'read',
        ],
        'admin_orders' => [
            'platform_owner' => 'edit', 'admin' => 'edit',
        ],
        'admin_products' => [
            'platform_owner' => 'edit', 'admin' => 'edit',
        ],
        'admin_users' => [
            'platform_owner' => 'edit', 'admin' => 'read',
        ],
        'admin_promotions' => [
            'platform_owner' => 'edit', 'admin' => 'edit',
        ],
        'admin_sales_performance' => [
            'platform_owner' => 'edit', 'admin' => 'edit',
        ],
        'rbac_matrix' => [
            'platform_owner' => 'edit', 'admin' => 'read',
        ],
        'field_ops' => [
            'platform_owner' => 'edit', 'admin' => 'edit',
            'sales' => 'read', 'driver' => 'read',
        ],
        'driver_roster' => [
            'platform_owner' => 'edit', 'admin' => 'edit',
        ],
        // Phase 9 AI action surfaces: platform_owner equivalent to admin,
        // every other role stays `none` (no row at all).
        'ai_actions' => [
            'platform_owner' => 'edit', 'admin' => 'edit',
        ],
        'supply_chain' => [
            'platform_owner' => 'edit', 'admin' => 'edit',
        ],
    ];

    public function run(): void
    {
        foreach (MenuDefinition::CATALOG as $menu) {
            MenuDefinition::updateOrCreate(
                ['key' => $menu['key']],
                [
                    'label' => $menu['label'],
                    'group' => $menu['group'],
                    'sort_order' => $menu['sort'],
                ],
            );
        }

        foreach (self::MATRIX as $menuKey => $roles) {
            foreach ($roles as $role => $level) {
                RoleMenuAccess::updateOrCreate(
                    ['role' => $role, 'menu_key' => $menuKey],
                    ['level' => $level],
                );
            }
        }
    }
}
