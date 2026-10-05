<?php

namespace App\Console\Commands;

use App\Services\Scoring\LotScoreManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scores:refresh')]
#[Description('Recompute the environmental footprint and the transparency score of every lot')]
class RefreshScores extends Command
{
    public function handle(LotScoreManager $scores): int
    {
        $count = $scores->refreshAll();

        $this->components->info("Lots recalculés : {$count}");

        return self::SUCCESS;
    }
}
