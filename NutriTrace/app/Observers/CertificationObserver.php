<?php

namespace App\Observers;

use App\Models\Certification;
use App\Services\Scoring\LotScoreManager;

class CertificationObserver
{
    public function __construct(private readonly LotScoreManager $scores) {}

    /**
     * A new, reviewed, expired or removed certification changes the transparency score.
     */
    public function saved(Certification $certification): void
    {
        $this->refresh($certification);
    }

    public function deleted(Certification $certification): void
    {
        $this->refresh($certification);
    }

    private function refresh(Certification $certification): void
    {
        $certification->affectedLots()->each(fn ($lot) => $this->scores->refreshTrust($lot));
    }
}
