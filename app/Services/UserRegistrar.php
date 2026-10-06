<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserRegistrar
{
    /**
     * Create an account from validated registration data. Consumers are active at once;
     * professional accounts get their organization and wait for an admin approval.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        $role = UserRole::from($data['role']);

        return DB::transaction(function () use ($data, $role) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
            ]);

            $user->forceFill([
                'role' => $role,
                'account_status' => $role->isProfessional() ? AccountStatus::PENDING : AccountStatus::APPROVED,
                'is_active' => ! $role->isProfessional(),
            ])->save();

            if ($role->isProfessional()) {
                $user->organization()->create([
                    'name' => $data['organization_name'],
                    'city' => $data['organization_city'],
                    'address' => $data['organization_address'] ?? null,
                    'registration_number' => $data['registration_number'] ?? null,
                ]);
            }

            return $user;
        });
    }
}
