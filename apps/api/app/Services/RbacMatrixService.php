<?php

namespace App\Services;

use App\Models\MenuDefinition;
use App\Models\RoleMenuAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Read + write access to the RBAC role x menu matrix.
 *
 * Single source of truth for the *shape* of a role's access map, shared by
 * `GET /auth/me` (T5) and `GET /admin/rbac/matrix` (T4) so the frontend always
 * receives the same contract: every menu key present, explicit `none` when no
 * row exists.
 */
class RbacMatrixService
{
    /** The seven roles, in the fixed order used by the spec table. */
    public const ROLES = [
        'platform_owner',
        'admin',
        'outlet',
        'supplier',
        'sales',
        'driver',
        'finance',
    ];

    /**
     * Access map for a single role: menu_key => level, all 21 keys explicit.
     * Missing rows become `none`.
     *
     * @return array<string, string>
     */
    public function mapForRole(string $role): array
    {
        $levels = RoleMenuAccess::query()
            ->where('role', $role)
            ->pluck('level', 'menu_key')
            ->all();

        return $this->mapForRoleFrom($levels);
    }

    /**
     * The full matrix: role => (menu_key => level) for all 7 roles.
     *
     * @return array<string, array<string, string>>
     */
    public function fullMatrix(): array
    {
        // One query for every cell, then shape in memory (avoids 7x21 queries).
        $rows = RoleMenuAccess::query()->get(['role', 'menu_key', 'level']);

        $byRole = [];
        foreach ($rows as $row) {
            $byRole[$row->role][$row->menu_key] = $row->level;
        }

        $matrix = [];
        foreach (self::ROLES as $role) {
            $matrix[$role] = $this->mapForRoleFrom($byRole[$role] ?? []);
        }

        return $matrix;
    }

    /**
     * Apply a set of cells atomically (all-or-nothing).
     *
     * The caller is expected to have validated role/menu_key/level already.
     * Enforces the two write rules here, BEFORE any mutation, inside a
     * transaction so a later failure cannot leave a partial write:
     *   - a non-owner (admin) may not touch the platform_owner row → 403
     *   - the actor may not remove their own rbac_matrix access → 422
     *
     * @param  list<array{role:string, menu_key:string, level:string}>  $cells
     */
    public function applyCells(User $actor, array $cells): void
    {
        $isOwner = $actor->role === 'platform_owner';

        // Pre-flight: no writes happen until every rule passes.
        foreach ($cells as $cell) {
            if (! $isOwner && $cell['role'] === 'platform_owner') {
                abort(403, 'Unauthorized. Only platform owners can modify the platform_owner row.');
            }
        }

        foreach ($cells as $cell) {
            if (
                $cell['role'] === $actor->role
                && $cell['menu_key'] === 'rbac_matrix'
                && $cell['level'] === RoleMenuAccess::LEVEL_NONE
            ) {
                abort(422, 'You cannot remove your own access to rbac_matrix.');
            }
        }

        DB::transaction(function () use ($cells): void {
            foreach ($cells as $cell) {
                if ($cell['level'] === RoleMenuAccess::LEVEL_NONE) {
                    // `none` is represented by the absence of a row.
                    RoleMenuAccess::query()
                        ->where('role', $cell['role'])
                        ->where('menu_key', $cell['menu_key'])
                        ->delete();

                    continue;
                }

                RoleMenuAccess::updateOrCreate(
                    ['role' => $cell['role'], 'menu_key' => $cell['menu_key']],
                    ['level' => $cell['level']],
                );
            }
        });
    }

    /**
     * Build a full 21-key map from an already-fetched role slice.
     *
     * @param  array<string, string>  $levels
     * @return array<string, string>
     */
    private function mapForRoleFrom(array $levels): array
    {
        $map = [];
        foreach (MenuDefinition::keys() as $menuKey) {
            $level = $levels[$menuKey] ?? RoleMenuAccess::LEVEL_NONE;
            $map[$menuKey] = in_array($level, RoleMenuAccess::LEVELS, true)
                ? $level
                : RoleMenuAccess::LEVEL_NONE;
        }

        return $map;
    }
}
