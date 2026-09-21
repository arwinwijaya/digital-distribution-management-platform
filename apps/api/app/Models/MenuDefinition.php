<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reference data for the RBAC menu matrix.
 *
 * `key` is the string primary key shared with `role_menu_access.menu_key`
 * and with the frontend `NavItem.key`.
 */
class MenuDefinition extends Model
{
    /**
     * Canonical menu catalog in display order — the single source of truth for
     * the 19 menu keys shared by the seeder, RbacMatrixService and the frontend
     * NavItem list. Kept as a constant (not only DB rows) so the RBAC endpoints
     * can render a full 19-key map even on a fresh DB where the seed has not run.
     *
     * @var list<array{key:string, label:string, group:string, sort:int}>
     */
    public const CATALOG = [
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

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'label',
        'group',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function access(): HasMany
    {
        return $this->hasMany(RoleMenuAccess::class, 'menu_key', 'key');
    }

    /**
     * All menu keys in display order.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::CATALOG, 'key');
    }
}
