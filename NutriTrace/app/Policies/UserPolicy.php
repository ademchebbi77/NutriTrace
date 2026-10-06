<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->is($model);
    }

    public function update(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->is($model);
    }

    /**
     * Approve or reject a professional account awaiting review.
     */
    public function review(User $user, User $model): bool
    {
        return $user->isAdmin() && $model->role->isProfessional() && $model->isPending();
    }

    /**
     * Activate or deactivate an account. Admins cannot switch admin accounts off.
     */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $model->isAdmin();
    }
}
