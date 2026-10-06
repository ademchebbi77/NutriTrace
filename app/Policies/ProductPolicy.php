<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->isAdmin() || $product->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR);
    }

    public function update(User $user, Product $product): bool
    {
        return $product->isOwnedBy($user);
    }

    /**
     * A product that already has lots must be archived instead, to keep the history.
     */
    public function delete(User $user, Product $product): bool
    {
        return $product->isOwnedBy($user) && ! $product->lots()->exists();
    }
}
