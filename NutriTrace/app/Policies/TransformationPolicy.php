<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Transformation;
use App\Models\User;

class TransformationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::TRANSFORMATEUR);
    }

    public function view(User $user, Transformation $transformation): bool
    {
        return $user->isAdmin() || $transformation->transformer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::TRANSFORMATEUR);
    }
}
