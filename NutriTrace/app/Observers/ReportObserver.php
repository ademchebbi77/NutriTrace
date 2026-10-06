<?php

namespace App\Observers;

use App\Models\Report;
use App\Services\Scoring\LotScoreManager;

class ReportObserver
{
    public function __construct(private readonly LotScoreManager $scores) {}

    /**
     * Open reports weigh on the transparency score; closing one lifts the penalty.
     */
    public function saved(Report $report): void
    {
        $report->affectedLots()->each(fn ($lot) => $this->scores->refreshTrust($lot));
    }
}
