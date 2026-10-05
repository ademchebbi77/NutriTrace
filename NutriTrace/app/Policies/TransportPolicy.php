<?php

namespace App\Policies;

use App\Enums\TransportStatus;
use App\Enums\UserRole;
use App\Models\Transport;
use App\Models\User;

class TransportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::ADMIN, UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR);
    }

    public function view(User $user, Transport $transport): bool
    {
        return $user->isAdmin() || in_array($user->id, [$transport->shipper_id, $transport->recipient_id], true);
    }

    /**
     * The shipper can correct the mode and distance while the lot is on the road.
     */
    public function update(User $user, Transport $transport): bool
    {
        return $transport->shipper_id === $user->id && $transport->status === TransportStatus::IN_TRANSIT;
    }
}
