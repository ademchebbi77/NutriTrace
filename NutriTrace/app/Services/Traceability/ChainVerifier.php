<?php

namespace App\Services\Traceability;

use App\Models\Lot;
use App\Models\TraceabilityEvent;

class ChainVerifier
{
    /**
     * Recompute every hash of the lot's event chain, in insertion order.
     * The chain is broken as soon as a stored hash or a link to the previous event differs.
     */
    public function verify(Lot $lot): ChainStatus
    {
        $events = TraceabilityEvent::query()->where('lot_id', $lot->id)->orderBy('id')->get();

        $expectedPrevious = TraceabilityEvent::GENESIS_HASH;

        foreach ($events as $event) {
            if ($event->previous_hash !== $expectedPrevious || TraceabilityRecorder::hash($event) !== $event->hash) {
                return new ChainStatus(false, $events->count(), $event->id);
            }

            $expectedPrevious = $event->hash;
        }

        return new ChainStatus(true, $events->count());
    }

    /**
     * Verify the lot and every upstream lot it was made from.
     */
    public function verifyJourney(Journey $journey): ChainStatus
    {
        $total = 0;

        foreach ($journey->lots() as $lot) {
            $status = $this->verify($lot);
            $total += $status->events;

            if (! $status->valid) {
                return new ChainStatus(false, $total, $status->brokenEventId);
            }
        }

        return new ChainStatus(true, $total);
    }
}
