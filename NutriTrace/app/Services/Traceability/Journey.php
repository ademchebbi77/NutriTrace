<?php

namespace App\Services\Traceability;

use App\Models\Lot;
use App\Models\Organization;
use App\Models\Production;
use App\Models\TraceabilityEvent;
use App\Models\Transport;
use Illuminate\Support\Collection;

/**
 * Full journey of a lot: its own events plus, recursively, the journeys of the
 * lots it was made from. Built by JourneyBuilder.
 */
final class Journey
{
    /**
     * @param  Collection<int, TraceabilityEvent>  $events  Events of this lot, oldest first.
     * @param  list<array{lot: Lot, quantity_used: float, journey: Journey}>  $sources
     */
    public function __construct(
        public readonly Lot $lot,
        public readonly Collection $events,
        public readonly array $sources = [],
    ) {}

    /**
     * This lot and every upstream lot, the farm lots first.
     *
     * @return Collection<int, Lot>
     */
    public function lots(): Collection
    {
        return collect($this->sources)
            ->flatMap(fn (array $source) => $source['journey']->lots())
            ->push($this->lot)
            ->unique('id')
            ->values();
    }

    /**
     * Every event of the journey in chronological order, from the farm to the shelf.
     *
     * @return Collection<int, TraceabilityEvent>
     */
    public function allEvents(): Collection
    {
        return collect($this->sources)
            ->flatMap(fn (array $source) => $source['journey']->allEvents())
            ->concat($this->events)
            ->unique('id')
            ->sort(fn (TraceabilityEvent $a, TraceabilityEvent $b) => [$a->occurred_at, $a->id] <=> [$b->occurred_at, $b->id])
            ->values();
    }

    /**
     * Farm productions at the origin of the lot.
     *
     * @return Collection<int, Production>
     */
    public function productions(): Collection
    {
        return $this->lots()->map(fn (Lot $lot) => $lot->production)->filter()->values();
    }

    /**
     * @return Collection<int, Transport>
     */
    public function transports(): Collection
    {
        return $this->lots()->flatMap(fn (Lot $lot) => $lot->transports)->values();
    }

    /**
     * Organizations that took part in the journey.
     *
     * @return Collection<int, Organization>
     */
    public function organizations(): Collection
    {
        return $this->allEvents()
            ->map(fn (TraceabilityEvent $event) => $event->actor?->organization)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Geolocated stops in travel order, for the map. Consecutive duplicates are merged.
     *
     * @return list<array{lat: float, lng: float, label: string, type: string}>
     */
    public function points(): array
    {
        $points = [];

        foreach ($this->allEvents() as $event) {
            if (! $event->hasCoordinates()) {
                continue;
            }

            $last = end($points);

            if ($last && abs($last['lat'] - $event->latitude) < 0.0001 && abs($last['lng'] - $event->longitude) < 0.0001) {
                continue;
            }

            $points[] = [
                'lat' => $event->latitude,
                'lng' => $event->longitude,
                'label' => (string) $event->location,
                'type' => $event->event_type->label(),
            ];
        }

        return $points;
    }

    public function isTransformed(): bool
    {
        return $this->sources !== [];
    }
}
