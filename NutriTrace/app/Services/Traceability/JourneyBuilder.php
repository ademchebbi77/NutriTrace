<?php

namespace App\Services\Traceability;

use App\Models\Lot;

class JourneyBuilder
{
    private const RELATIONS = [
        'product.category',
        'production.producer.organization',
        'transformation.transformer.organization',
        'transformation.inputs',
        'transports',
        'distributions.distributor.organization',
        'events.actor.organization',
    ];

    /**
     * Rebuild the journey of a lot, following transformations back to the farms
     * (e.g. olive oil lot -> olive lot -> farm in Sfax).
     */
    public function build(Lot $lot): Journey
    {
        $visited = [];

        return $this->journeyOf($lot, $visited);
    }

    /**
     * @param  array<int, true>  $visited  Guards against a lot appearing twice in its own ancestry.
     */
    private function journeyOf(Lot $lot, array &$visited): Journey
    {
        $visited[$lot->id] = true;

        $lot->loadMissing(self::RELATIONS);

        $sources = [];

        foreach ($lot->transformation?->inputs ?? [] as $input) {
            if (isset($visited[$input->lot_id])) {
                continue;
            }

            $sourceLot = Lot::query()->find($input->lot_id);

            if ($sourceLot) {
                $sources[] = [
                    'lot' => $sourceLot,
                    'quantity_used' => $input->quantity_used,
                    'journey' => $this->journeyOf($sourceLot, $visited),
                ];
            }
        }

        $events = $lot->events
            ->sort(fn ($a, $b) => [$a->occurred_at, $a->id] <=> [$b->occurred_at, $b->id])
            ->values()
            ->each(fn ($event) => $event->setRelation('lot', $lot));

        return new Journey($lot, $events, $sources);
    }
}
