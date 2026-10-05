<?php

namespace App\Policies;

use App\Enums\DistributionStatus;
use App\Enums\UserRole;
use App\Models\Distribution;
use App\Models\User;

class DistributionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::DISTRIBUTEUR);
    }

    public function view(User $user, Distribution $distribution): bool
    {
        return $user->isAdmin() || in_array($user->id, [$distribution->distributor_id, $distribution->sender_id], true);
    }

    /**
     * Only the distributor the lot was sent to can confirm or refuse its reception.
     */
    public function respond(User $user, Distribution $distribution): bool
    {
        return $distribution->distributor_id === $user->id && $distribution->isPending();
    }

    public function markInStore(User $user, Distribution $distribution): bool
    {
        return $distribution->distributor_id === $user->id && $distribution->status === DistributionStatus::RECEIVED;
    }

    /**
     * Sales and labels concern lots on the shelves that still have stock.
     */
    public function sell(User $user, Distribution $distribution): bool
    {
        return $distribution->distributor_id === $user->id
            && $distribution->status === DistributionStatus::IN_STORE
            && $distribution->lot->quantity > 0;
    }
}
