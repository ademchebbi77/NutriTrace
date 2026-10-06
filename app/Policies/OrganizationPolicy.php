<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->isAdmin() || $organization->user_id === $user->id;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $organization->user_id === $user->id;
    }

    public function verify(User $user, Organization $organization): bool
    {
        return $user->isAdmin();
    }
}
