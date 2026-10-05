<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Reviews are written by consumers, not by the professionals of the chain.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::CONSOMMATEUR) && $user->canAccessBackOffice() && $user->hasVerifiedEmail();
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $user->isAdmin();
    }
}
