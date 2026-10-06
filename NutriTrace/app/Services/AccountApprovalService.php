<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccountReviewed;
use Illuminate\Support\Facades\DB;

class AccountApprovalService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(User $user, User $admin): void
    {
        DB::transaction(function () use ($user, $admin) {
            $this->review($user, $admin, AccountStatus::APPROVED, active: true);
            $this->audit->log('account.approved', $user, $user->email);
        });

        $user->notify(new AccountReviewed(approved: true));
    }

    public function reject(User $user, User $admin, string $reason): void
    {
        DB::transaction(function () use ($user, $admin, $reason) {
            $this->review($user, $admin, AccountStatus::REJECTED, active: false, reason: $reason);
            $this->audit->log('account.rejected', $user, $user->email, ['reason' => $reason]);
        });

        $user->notify(new AccountReviewed(approved: false, reason: $reason));
    }

    /**
     * Admin on/off switch, independent from the approval workflow.
     */
    public function setActive(User $user, bool $active): void
    {
        $user->forceFill(['is_active' => $active])->save();

        $this->audit->log($active ? 'account.activated' : 'account.deactivated', $user, $user->email);
    }

    public function setOrganizationVerified(Organization $organization, User $admin, bool $verified): void
    {
        $organization->forceFill([
            'is_verified' => $verified,
            'verified_by' => $verified ? $admin->getKey() : null,
            'verified_at' => $verified ? now() : null,
        ])->save();

        $this->audit->log($verified ? 'organization.verified' : 'organization.unverified', $organization, $organization->name);
    }

    private function review(User $user, User $admin, AccountStatus $status, bool $active, ?string $reason = null): void
    {
        $user->forceFill([
            'account_status' => $status,
            'is_active' => $active,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->getKey(),
            'reviewed_at' => now(),
        ])->save();
    }
}
