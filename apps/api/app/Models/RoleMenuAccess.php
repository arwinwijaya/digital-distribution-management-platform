<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cell of the RBAC matrix: a role's access level to a menu.
 *
 * Composite primary key (role, menu_key). A missing row means "none".
 */
class RoleMenuAccess extends Model
{
    public const LEVEL_NONE = 'none';

    public const LEVEL_READ = 'read';

    public const LEVEL_EDIT = 'edit';

    /** Allowed levels, ordered from least to most permissive. */
    public const LEVELS = [self::LEVEL_NONE, self::LEVEL_READ, self::LEVEL_EDIT];

    protected $table = 'role_menu_access';

    protected $primaryKey = 'role';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'role',
        'menu_key',
        'level',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MenuDefinition::class, 'menu_key', 'key');
    }

    /**
     * Ordinal rank used for comparison (none < read < edit).
     */
    public static function rank(string $level): int
    {
        $index = array_search($level, self::LEVELS, true);

        return $index === false ? 0 : $index;
    }
}
