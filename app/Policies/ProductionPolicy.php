<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Production;
use App\Models\User;

class ProductionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::PRODUCTEUR);
    }

    public function view(User $user, Production $production): bool
    {
        return $user->isAdmin() || $production->producer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::PRODUCTEUR);
    }

    public function update(User $user, Production $production): bool
    {
        return $production->producer_id === $user->id && $production->isEditable();
    }

    public function delete(User $user, Production $production): bool
    {
        return $this->update($user, $production);
    }
}
