<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('make:admin {--name= : Full name} {--email= : Email address} {--password= : Password (prompted when omitted)}')]
#[Description('Create an administrator account (admins can never register publicly)')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Nom complet', required: true),
            'email' => $this->option('email') ?? text('Adresse e-mail', required: true),
            'password' => $this->option('password') ?? password('Mot de passe', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = new User($data);

        $admin->forceFill([
            'role' => UserRole::ADMIN,
            'account_status' => AccountStatus::APPROVED,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->components->info("Administrateur créé : {$admin->email}");

        return self::SUCCESS;
    }
}
