<?php

namespace App\Services;

use App\Models\RoleAssignmentAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserRoleService
{
    /**
     * Assign a role to a target user.
     *
     *  - Creates an audit record for every actual role change.
     *  - Invalidates the target user's current JWT so old claims
     *    are no longer usable (force re-login).
     *  - Returns the refreshed target user.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 when role is invalid
     */
    public function assign(User $actor, int $targetUserId, string $role): User
    {
        $allowed = [
            'admin',
            'supplier',
            'outlet',
            'sales',
            'driver',
            'finance',
            'platform_owner',
        ];

        abort_unless(in_array($role, $allowed, true), 422, 'Invalid role.');

        return DB::transaction(function () use ($actor, $targetUserId, $role): User {
            $target = User::query()->lockForUpdate()->findOrFail($targetUserId);

            abort_unless($target->is_active, 422, 'Cannot assign role to an inactive user.');

            if ($target->role === $role) {
                return $target->fresh();
            }

            $fromRole = $target->role;

            $target->forceFill(['role' => $role])->save();

            RoleAssignmentAudit::create([
                'target_user_id' => $target->id,
                'actor_user_id'  => $actor->id,
                'from_role'      => $fromRole,
                'to_role'        => $role,
                'action'         => RoleAssignmentAudit::ASSIGNED,
            ]);

            // Invalidate old JWT — increment jwt_version so stale tokens
            // (with the old claim) are rejected by RejectStaleJwt middleware.
            $target->forceFill(['jwt_version' => ((int) ($target->jwt_version ?? 0)) + 1])->save();

            return $target->fresh();
        });
    }
}
