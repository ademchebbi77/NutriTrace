<?php

namespace App\Services\Scoring;

use App\Enums\TransportStatus;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\Production;
use App\Services\GeoDistance;
use App\Services\Traceability\Journey;

/**
 * "Local" is measured, never self-declared: a lot is local when every farm it
 * comes from lies within the configured radius of its final destination.
 */
class LocalRule
{
    public function __construct(private readonly GeoDistance $distance) {}

    /**
     * @return array{is_local: ?bool, distance_km: ?float, radius_km: float}
     */
    public function evaluate(Journey $journey): array
    {
        $radius = (float) config('trust.local_radius_km');
        $destination = $this->finalDestination($journey->lot);
        $origins = $journey->productions()->filter(fn (Production $production) => $production->hasCoordinates());

        // Unknown as long as one end of the journey is not geolocated.
        if ($destination === null || $origins->isEmpty() || $origins->count() < $journey->productions()->count()) {
            return ['is_local' => null, 'distance_km' => null, 'radius_km' => $radius];
        }

        $farthest = $origins
            ->map(fn (Production $production) => $this->distance->km($production->latitude, $production->longitude, $destination[0], $destination[1]))
            ->max();

        return ['is_local' => $farthest <= $radius, 'distance_km' => $farthest, 'radius_km' => $radius];
    }

    /**
     * Where the lot ended up: the store of its accepted distribution, otherwise
     * the destination of its last delivered transport.
     *
     * @return array{float, float}|null
     */
    private function finalDestination(Lot $lot): ?array
    {
        $distribution = $lot->distributions
            ->filter(fn (Distribution $distribution) => $distribution->isAccepted() && $distribution->hasCoordinates())
            ->sortByDesc('id')
            ->first();

        if ($distribution) {
            return [$distribution->latitude, $distribution->longitude];
        }

        $transport = $lot->transports
            ->filter(fn ($transport) => $transport->status === TransportStatus::DELIVERED && $transport->destination_latitude !== null)
            ->sortByDesc('id')
            ->first();

        return $transport ? [$transport->destination_latitude, $transport->destination_longitude] : null;
    }
}
