<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isAdmin();
    }

    /**
     * A category still used by products cannot be removed.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->isAdmin() && ! $category->products()->exists();
    }
}
