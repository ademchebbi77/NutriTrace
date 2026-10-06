<?php

namespace App\Services\Scoring;

use App\Enums\CertificationStatus;
use App\Enums\TransportStatus;
use App\Models\Lot;
use App\Models\Report;
use App\Services\Traceability\ChainVerifier;
use App\Services\Traceability\Journey;
use App\Services\Traceability\JourneyBuilder;

/**
 * Transparency score (0-100) of a lot: how much of what it claims is documented
 * and verifiable. Weights and penalties come from config/trust.php.
 */
class TrustScoreCalculator
{
    public function __construct(
        private readonly JourneyBuilder $journeys,
        private readonly ChainVerifier $verifier,
        private readonly ChainConsistencyChecker $consistency,
    ) {}

    public function calculate(Lot $lot, ?Journey $journey = null): TrustScore
    {
        $journey ??= $this->journeys->build($lot);
        $lot->loadMissing(['certifications', 'product.certifications', 'environmentalImpact']);
        $weights = config('trust.weights');

        $components = [
            $this->chainCompleteness($journey, (float) $weights['chain_completeness']),
            $this->verifiedActors($journey, (float) $weights['verified_actors']),
            $this->certifications($lot, (float) $weights['certifications']),
            $this->environmentalData($lot, (float) $weights['environmental_data']),
            $this->chainIntegrity($journey, (float) $weights['chain_integrity']),
        ];

        $penalties = $this->penalties($lot, $journey);

        $total = array_sum(array_column($components, 'points')) - array_sum(array_column($penalties, 'points'));

        return new TrustScore((int) round(max(0, min(100, $total))), $components, $penalties);
    }

    /**
     * Recompute and cache the score on the lot (used by lists and catalog filters).
     */
    public function refresh(Lot $lot): TrustScore
    {
        $score = $this->calculate($lot);

        Lot::query()->whereKey($lot->id)->update(['trust_score' => $score->score]);
        $lot->trust_score = $score->score;
        $lot->syncOriginalAttribute('trust_score');

        return $score;
    }

    /**
     * @return array{key: string, label: string, points: float, max: float, detail: string}
     */
    private function chainCompleteness(Journey $journey, float $max): array
    {
        $lot = $journey->lot;
        $productions = $journey->productions();
        $transports = $journey->transports()->where('status', TransportStatus::DELIVERED);
        $accepted = $lot->distributions->filter->isAccepted();

        $checks = [
            'origin_known' => $productions->isNotEmpty(),
            'origin_geolocated' => $productions->isNotEmpty() && $productions->every->hasCoordinates(),
            'transport_documented' => $transports->isNotEmpty() && $transports->every(fn ($transport) => $transport->distance_km !== null),
            'distribution_recorded' => $accepted->isNotEmpty(),
            // Only required when the lot was transformed.
            'transformation_documented' => ! $journey->isTransformed() || filled($lot->transformation?->process_description),
        ];

        $missing = collect($checks)->reject(fn (bool $ok) => $ok)->keys()->map(fn (string $key) => __('trust.checks.'.$key));

        return $this->component(
            'chain_completeness',
            $max * count(array_filter($checks)) / count($checks),
            $max,
            $missing->isEmpty() ? __('trust.details.chain_complete') : __('trust.details.chain_missing', ['items' => $missing->join(', ')]),
        );
    }

    /**
     * @return array{key: string, label: string, points: float, max: float, detail: string}
     */
    private function verifiedActors(Journey $journey, float $max): array
    {
        $organizations = $journey->organizations();
        $verified = $organizations->where('is_verified', true)->count();
        $total = $organizations->count();

        return $this->component(
            'verified_actors',
            $total === 0 ? 0 : $max * $verified / $total,
            $max,
            __('trust.details.verified_actors', ['verified' => $verified, 'total' => $total]),
        );
    }

    /**
     * @return array{key: string, label: string, points: float, max: float, detail: string}
     */
    private function certifications(Lot $lot, float $max): array
    {
        $valid = $lot->validCertifications();
        $proven = $valid->filter->hasProof();

        [$ratio, $detail] = match (true) {
            $valid->isEmpty() => [0.0, __('trust.details.no_certification')],
            $proven->isEmpty() => [0.6, __('trust.details.certification_without_proof', ['count' => $valid->count()])],
            default => [1.0, __('trust.details.certification_proven', ['count' => $proven->count()])],
        };

        return $this->component('certifications', $max * $ratio, $max, $detail);
    }

    /**
     * @return array{key: string, label: string, points: float, max: float, detail: string}
     */
    private function environmentalData(Lot $lot, float $max): array
    {
        $impact = $lot->environmentalImpact;

        $indicators = [
            $impact?->co2_kg !== null,
            $impact?->water_l !== null,
            $impact?->energy_kwh !== null,
            $impact?->waste_kg !== null,
            $impact?->packaging_co2_kg !== null,
            ($impact?->food_miles_km ?? 0) > 0,
        ];

        $known = count(array_filter($indicators));

        return $this->component(
            'environmental_data',
            $max * $known / count($indicators),
            $max,
            __('trust.details.environmental_data', ['known' => $known, 'total' => count($indicators)]),
        );
    }

    /**
     * @return array{key: string, label: string, points: float, max: float, detail: string}
     */
    private function chainIntegrity(Journey $journey, float $max): array
    {
        $status = $this->verifier->verifyJourney($journey);
        $valid = $status->valid && $status->events > 0;

        return $this->component(
            'chain_integrity',
            $valid ? $max : 0,
            $max,
            $valid ? __('trust.details.chain_intact', ['count' => $status->events]) : __('trust.details.chain_broken'),
        );
    }

    /**
     * @return list<array{key: string, label: string, points: float, count: int}>
     */
    private function penalties(Lot $lot, Journey $journey): array
    {
        $config = config('trust.penalties');
        $certifications = $lot->allCertifications();

        $counts = [
            'expired_certification' => $certifications->filter(fn ($c) => $c->status === CertificationStatus::EXPIRED
                || ($c->status === CertificationStatus::VERIFIED && $c->isPastExpiration()))->count(),
            'rejected_certification' => $certifications->where('status', CertificationStatus::REJECTED)->count(),
            'open_report' => Report::query()->open()->concerningLot($lot)->count(),
            'chain_gap' => count($this->consistency->issues($journey)),
        ];

        $penalties = [];
        $remaining = (float) $config['max_total'];

        foreach ($counts as $key => $count) {
            if ($count === 0) {
                continue;
            }

            // The total removed never exceeds the configured cap.
            $points = min($remaining, $count * (float) $config[$key]);
            $remaining -= $points;

            $penalties[] = ['key' => $key, 'label' => __('trust.penalties.'.$key), 'points' => $points, 'count' => $count];
        }

        return $penalties;
    }

    /**
     * @return array{key: string, label: string, points: float, max: float, detail: string}
     */
    private function component(string $key, float $points, float $max, string $detail): array
    {
        return ['key' => $key, 'label' => __('trust.components.'.$key), 'points' => round($points, 1), 'max' => $max, 'detail' => $detail];
    }
}
