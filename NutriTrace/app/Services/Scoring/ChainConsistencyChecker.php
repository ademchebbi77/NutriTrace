<?php

namespace App\Services\Scoring;

use App\Enums\TransportStatus;
use App\Models\Lot;
use App\Services\Traceability\Journey;

/**
 * Looks for dates and places that contradict each other along a journey.
 */
class ChainConsistencyChecker
{
    /**
     * @return list<array{code: string, message: string, related: string}>
     */
    public function issues(Journey $journey): array
    {
        $issues = [];

        foreach ($journey->lots() as $lot) {
            $issues = [...$issues, ...$this->lotIssues($lot)];
        }

        return $issues;
    }

    /**
     * @return list<array{code: string, message: string, related: string}>
     */
    private function lotIssues(Lot $lot): array
    {
        $issues = [];
        $add = function (string $code) use (&$issues, $lot) {
            $issues[] = ['code' => $code, 'message' => __('warnings.consistency.'.$code), 'related' => $lot->lot_number];
        };

        if ($transformation = $lot->transformation) {
            $transformation->loadMissing('inputs.lot');

            foreach ($transformation->inputs as $input) {
                if ($transformation->transformation_date->lt($input->lot->production_date)) {
                    $add('transformation_before_source');
                }
            }
        }

        foreach ($lot->transports as $transport) {
            if ($transport->status === TransportStatus::CANCELLED) {
                continue;
            }

            if ($transport->departure_date->toDateString() < $lot->production_date->toDateString()) {
                $add('transport_before_production');
            }

            if ($transport->arrival_date && $transport->arrival_date->lt($transport->departure_date)) {
                $add('arrival_before_departure');
            }

            if ($transport->distance_km === null) {
                $add('transport_without_distance');
            }
        }

        foreach ($lot->distributions as $distribution) {
            if (! $distribution->isAccepted()) {
                continue;
            }

            if ($distribution->reception_date && $distribution->reception_date->lt($distribution->distribution_date)) {
                $add('reception_before_shipping');
            }

            if ($lot->expiration_date && $distribution->distribution_date->gt($lot->expiration_date)) {
                $add('distributed_after_expiration');
            }
        }

        if ($lot->production && ! $lot->production->hasCoordinates()) {
            $add('origin_not_geolocated');
        }

        return $issues;
    }
}
