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
 * (spec listed 18 menus; the app has 19). 19 menus x 7 roles = 77 non-none cells.
 *
 * Only non-`none` cells are persisted; a missing row is interpreted as `none`
 * by the Rbac middleware and by RbacMatrixService. Fully idempotent via
 * updateOrCreate, so `php artisan db:seed` can be re-run safely.
 */
class RbacMatrixSeeder extends Seeder
{
    /**
     * Menu reference data in display order.
     *
     * @var list<array{key:string, label:string, group:string, sort:int}>
     */
    private const MENUS = [
        ['key' => 'dashboard',              'label' => 'Dasbor',            'group' => 'operasional', 'sort' => 1],
        ['key' => 'orders',                 'label' => 'Pesanan',           'group' => 'operasional', 'sort' => 2],
        ['key' => 'products',               'label' => 'Produk',            'group' => 'operasional', 'sort' => 3],
        ['key' => 'outlets',                'label' => 'Outlet',            'group' => 'operasional', 'sort' => 4],
        ['key' => 'marketplace',            'label' => 'Marketplace',       'group' => 'operasional', 'sort' => 5],
        ['key' => 'payments',               'label' => 'Pembayaran',        'group' => 'operasional', 'sort' => 6],
        ['key' => 'delivery',               'label' => 'Pengiriman',        'group' => 'operasional', 'sort' => 7],
        ['key' => 'sales',                  'label' => 'Sales',             'group' => 'operasional', 'sort' => 8],
        ['key' => 'invoices',               'label' => 'Invoice',           'group' => 'operasional', 'sort' => 9],
        ['key' => 'worktree',               'label' => 'Worktree',          'group' => 'operasional', 'sort' => 10],
        ['key' => 'analytics',              'label' => 'Analitik',          'group' => 'analitik',    'sort' => 11],
        ['key' => 'data_intelligence',      'label' => 'Data Intelligence', 'group' => 'analitik',    'sort' => 12],
        ['key' => 'operations',             'label' => 'Operasi',           'group' => 'analitik',    'sort' => 13],
        ['key' => 'admin_orders',           'label' => 'Approval Pesanan',  'group' => 'admin',       'sort' => 14],
        ['key' => 'admin_products',         'label' => 'Harga Produk',      'group' => 'admin',       'sort' => 15],
        ['key' => 'admin_users',            'label' => 'Kelola Pengguna',   'group' => 'admin',       'sort' => 16],
        ['key' => 'admin_promotions',       'label' => 'Kelola Promosi',    'group' => 'admin',       'sort' => 17],
        ['key' => 'admin_sales_performance','label' => 'Performa Sales',    'group' => 'admin',       'sort' => 18],
        ['key' => 'rbac_matrix',            'label' => 'Kelola Akses',      'group' => 'admin',       'sort' => 19],
    ];

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
    ];

    public function run(): void
    {
        foreach (self::MENUS as $menu) {
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
