<?php

namespace App\Services;

use App\Models\RoleAssignmentAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FinanceRoleService
{
    public function assign(User $actor, int $targetUserId): User
    {
        return DB::transaction(function () use ($actor, $targetUserId): User {
            $target = User::query()->lockForUpdate()->findOrFail($targetUserId);

            abort_unless($target->is_active, 422, 'Inactive users cannot receive the finance role.');

            if ($target->role === 'finance') {
                return $target;
            }

            $fromRole = $target->role;
            $target->forceFill(['role' => 'finance'])->save();

            RoleAssignmentAudit::create([
                'target_user_id' => $target->id,
                'actor_user_id' => $actor->id,
                'from_role' => $fromRole,
                'to_role' => 'finance',
                'action' => RoleAssignmentAudit::ASSIGNED,
            ]);

            return $target->fresh();
        });
    }

    public function remove(User $actor, int $targetUserId): User
    {
        return DB::transaction(function () use ($actor, $targetUserId): User {
            $target = User::query()->lockForUpdate()->findOrFail($targetUserId);

            if ($target->role !== 'finance') {
                return $target;
            }

            $target->forceFill(['role' => 'outlet'])->save();

            RoleAssignmentAudit::create([
                'target_user_id' => $target->id,
                'actor_user_id' => $actor->id,
                'from_role' => 'finance',
                'to_role' => 'outlet',
                'action' => RoleAssignmentAudit::REMOVED,
            ]);

            return $target->fresh();
        });
    }
}
