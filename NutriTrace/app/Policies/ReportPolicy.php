<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Report $report): bool
    {
        return $user->isAdmin() || $report->user_id === $user->id;
    }

    /**
     * Any active, verified account can report suspicious information.
     */
    public function create(User $user): bool
    {
        return $user->canAccessBackOffice() && $user->hasVerifiedEmail();
    }

    public function moderate(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }
}
