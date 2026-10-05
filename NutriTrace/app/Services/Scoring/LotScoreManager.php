<?php

namespace App\Services\Scoring;

use App\Models\Lot;
use App\Models\TransformationInput;

/**
 * Keeps the cached footprint and transparency score of lots up to date.
 * Observers call it whenever a record that feeds the scores changes.
 */
class LotScoreManager
{
    public function __construct(
        private readonly FootprintCalculator $footprint,
        private readonly TrustScoreCalculator $trust,
    ) {}

    /**
     * Recompute a lot, then every lot made from it (their footprint includes its own).
     */
    public function refresh(Lot|int $lot, array &$done = []): void
    {
        $id = $lot instanceof Lot ? $lot->id : $lot;

        if (isset($done[$id])) {
            return;
        }

        $done[$id] = true;

        // Always work on a fresh copy: relations cached on the given model may be stale.
        $fresh = Lot::query()->find($id);

        if (! $fresh) {
            return;
        }

        $this->footprint->refresh($fresh);
        $this->trust->refresh($fresh);

        TransformationInput::query()
            ->where('lot_id', $id)
            ->with('transformation:id,output_lot_id')
            ->get()
            ->each(fn (TransformationInput $input) => $this->refresh($input->transformation->output_lot_id, $done));
    }

    /**
     * Recompute every lot, sources before the lots made from them.
     * Used after the scoring settings change.
     *
     * @return int Number of lots recomputed.
     */
    public function refreshAll(): int
    {
        $done = [];

        Lot::query()->orderBy('id')->pluck('id')->each(fn (int $id) => $this->refresh($id, $done));

        return count($done);
    }

    /**
     * Recompute only the transparency score (certifications and reports do not change the footprint).
     */
    public function refreshTrust(Lot $lot): void
    {
        if ($fresh = Lot::query()->find($lot->id)) {
            $this->trust->refresh($fresh);
        }
    }
}
