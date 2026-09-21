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
}
