<?php

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\User;

class CertificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR);
    }

    public function view(User $user, Certification $certification): bool
    {
        return $user->isAdmin() || $certification->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR);
    }

    /**
     * A verified certificate is frozen: only pending or rejected ones can be corrected.
     */
    public function update(User $user, Certification $certification): bool
    {
        return $certification->owner_id === $user->id
            && in_array($certification->status, [CertificationStatus::PENDING, CertificationStatus::REJECTED], true);
    }

    public function delete(User $user, Certification $certification): bool
    {
        return $certification->owner_id === $user->id;
    }

    public function review(User $user, Certification $certification): bool
    {
        return $user->isAdmin() && $certification->status === CertificationStatus::PENDING;
    }
}
