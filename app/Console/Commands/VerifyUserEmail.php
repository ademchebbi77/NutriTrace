<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:verify {email : Email address of the account}')]
#[Description('Mark a user email as verified without the email link (development helper)')]
class VerifyUserEmail extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('Aucun compte ne correspond à cette adresse e-mail.');

            return self::FAILURE;
        }

        $user->markEmailAsVerified();

        $this->components->info("Adresse e-mail vérifiée : {$user->email}");

        return self::SUCCESS;
    }
}
