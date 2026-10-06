<?php

namespace App\Policies;

use App\Models\LotTransfer;
use App\Models\User;

class LotTransferPolicy
{
    public function view(User $user, LotTransfer $transfer): bool
    {
        return $user->isAdmin() || in_array($user->id, [$transfer->from_user_id, $transfer->to_user_id], true);
    }

    /**
     * Only the receiver can accept or refuse, and only once.
     */
    public function respond(User $user, LotTransfer $transfer): bool
    {
        return $transfer->to_user_id === $user->id && $transfer->isPending();
    }
}
