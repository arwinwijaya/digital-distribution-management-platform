<?php

namespace App\Policies;

use App\Models\User;
use App\Services\FinanceAuthorizationService;

/**
 * Central authorization policy for user / role management.
 *
 * platform_owner is a superset of admin:
 *  - every admin endpoint grants access to platform_owner
 *  - every platform_owner-only endpoint denies admin
 */
class UserPolicy
{
    public function __construct(
        private readonly FinanceAuthorizationService $authorization,
    ) {
    }

    /**
     * Admin-grade access: admin OR platform_owner.
     */
    public function viewAny(User $user): bool
    {
        return $this->authorization->isAdminOrOwner($user);
    }

    public function view(User $actor, User $target): bool
    {
        // Users may view their own profile; admins/owners may view anyone.
        if ($actor->getKey() === $target->getKey()) {
            return true;
        }

        return $this->authorization->isAdminOrOwner($actor);
    }

    /**
     * General role assignment is platform_owner-only; admin is denied.
     */
    public function assignRole(User $user): bool
    {
        return $this->authorization->isPlatformOwner($user);
    }

    /**
     * Profile update: any active authenticated user may update their own name/email.
     * Role changes are never allowed via profile update (handled by assignRole).
     */
    public function updateProfile(User $user): bool
    {
        return (bool) $user->is_active;
    }
}
