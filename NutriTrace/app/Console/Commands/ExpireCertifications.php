<?php

namespace App\Console\Commands;

use App\Services\CertificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('certifications:expire')]
#[Description('Mark verified certifications past their expiration date as expired and recompute the affected scores')]
class ExpireCertifications extends Command
{
    public function handle(CertificationService $certifications): int
    {
        $count = $certifications->expireOutdated();

        $this->components->info("Certifications expirées : {$count}");

        return self::SUCCESS;
    }
}
