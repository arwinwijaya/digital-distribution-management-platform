<?php

namespace App\Services;

use App\Models\User;

class FinanceAuthorizationService
{
    public function current(User $user): ?User
    {
        return User::query()->find($user->getKey());
    }

    public function hasCurrentRole(User $user, string $role): bool
    {
        return User::query()
            ->whereKey($user->getKey())
            ->where('role', $role)
            ->where('is_active', true)
            ->exists();
    }

    public function isAdmin(User $user): bool
    {
        return $this->hasCurrentRole($user, 'admin');
    }

    public function isPlatformOwner(User $user): bool
    {
        return $this->hasCurrentRole($user, 'platform_owner');
    }

    public function isFinance(User $user): bool
    {
        return $this->hasCurrentRole($user, 'finance');
    }

    /**
     * platform_owner is a superset of admin — every admin endpoint
     * must grant access to platform_owner.
     */
    public function isAdminOrOwner(User $user): bool
    {
        return $this->isAdmin($user) || $this->isPlatformOwner($user);
    }

    public function assertAdmin(User $user): void
    {
        abort_unless($this->isAdmin($user), 403, 'Unauthorized. Only admins can perform this action.');
    }

    /**
     * Superset assertion: allows admin OR platform_owner.
     */
    public function assertAdminOrOwner(User $user): void
    {
        abort_unless($this->isAdminOrOwner($user), 403, 'Unauthorized. Only admins can perform this action.');
    }

    /**
     * Strict platform_owner-only assertion: denies admin.
     */
    public function assertPlatformOwner(User $user): void
    {
        abort_unless($this->isPlatformOwner($user), 403, 'Unauthorized. Only platform owners can perform this action.');
    }

    public function assertFinance(User $user): void
    {
        abort_unless($this->isFinance($user), 403, 'Unauthorized. Finance role required.');
    }
}
