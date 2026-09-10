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

    public function isFinance(User $user): bool
    {
        return $this->hasCurrentRole($user, 'finance');
    }

    public function assertAdmin(User $user): void
    {
        abort_unless($this->isAdmin($user), 403, 'Unauthorized. Only admins can perform this action.');
    }

    public function assertFinance(User $user): void
    {
        abort_unless($this->isFinance($user), 403, 'Unauthorized. Finance role required.');
    }
}
