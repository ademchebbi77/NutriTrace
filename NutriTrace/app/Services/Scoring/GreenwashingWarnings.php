<?php

namespace App\Services\Scoring;

use App\Enums\CertificationStatus;
use App\Enums\CertificationType;
use App\Enums\ProductionMethod;
use App\Models\Certification;
use App\Models\Lot;
use App\Services\Traceability\ChainVerifier;
use App\Services\Traceability\Journey;
use App\Services\Traceability\JourneyBuilder;
use Illuminate\Support\Collection;

/**
 * Compares what a lot claims with what is actually proven, and lists the gaps.
 */
class GreenwashingWarnings
{
    public const HIGH = 'high';

    public const MEDIUM = 'medium';

    public const LOW = 'low';

    public function __construct(
        private readonly JourneyBuilder $journeys,
        private readonly ChainVerifier $verifier,
        private readonly ChainConsistencyChecker $consistency,
        private readonly LocalRule $localRule,
    ) {}

    /**
     * @return Collection<int, array{severity: string, code: string, message: string, related: ?string}>
     */
    public function for(Lot $lot, ?Journey $journey = null): Collection
    {
        $journey ??= $this->journeys->build($lot);
        $lot->loadMissing(['certifications', 'product.certifications', 'environmentalImpact']);

        $warnings = collect();
        $add = function (string $severity, string $code, ?string $related = null, array $replace = []) use ($warnings) {
            $warnings->push([
                'severity' => $severity,
                'code' => $code,
                'message' => __('warnings.messages.'.$code, $replace),
                'related' => $related,
            ]);
        };

        $certifications = $lot->allCertifications();

        // "Bio" claim without a verified certificate.
        foreach ($this->unprovenOrganicClaims($journey) as $related) {
            $add(self::HIGH, 'bio_unverified', $related);
        }

        foreach ($certifications as $certification) {
            if ($certification->status === CertificationStatus::EXPIRED || ($certification->status === CertificationStatus::VERIFIED && $certification->isPastExpiration())) {
                $add(self::MEDIUM, 'certificate_expired', $certification->name, ['date' => $certification->expiration_date?->format('d/m/Y')]);
            } elseif ($certification->status === CertificationStatus::REJECTED) {
                $add(self::HIGH, 'certificate_rejected', $certification->name);
            } elseif ($certification->status === CertificationStatus::PENDING) {
                $add(self::LOW, 'certificate_pending', $certification->name);
            } elseif (! $certification->hasProof()) {
                $add(self::LOW, 'certificate_without_proof', $certification->name);
            }
        }

        // "Local" claim contradicted by the distance actually travelled.
        if ($this->claimsLocal($lot, $certifications)) {
            $impact = $lot->environmentalImpact;
            $local = $this->localRule->evaluate($journey);
            $limit = (float) config('trust.local_claim_max_food_miles_km');

            if (($impact && $impact->food_miles_km > $limit) || $local['is_local'] === false) {
                $add(self::HIGH, 'local_claim_far', $lot->lot_number, [
                    'km' => number_format($impact?->food_miles_km ?? $local['distance_km'] ?? 0, 0, ',', ' '),
                ]);
            }
        }

        $impact = $lot->environmentalImpact;

        if (! $impact || $impact->co2_kg === null) {
            $add(self::MEDIUM, 'environmental_data_missing', $lot->lot_number);
        } elseif ($impact->water_l === null || $impact->energy_kwh === null) {
            $add(self::LOW, 'environmental_data_partial', $lot->lot_number);
        }

        foreach ($this->consistency->issues($journey) as $issue) {
            $warnings->push(['severity' => self::MEDIUM, 'code' => $issue['code'], 'message' => $issue['message'], 'related' => $issue['related']]);
        }

        if (! $this->verifier->verifyJourney($journey)->valid) {
            $add(self::HIGH, 'chain_broken', $lot->lot_number);
        }

        $unverified = $journey->organizations()->where('is_verified', false);

        if ($unverified->isNotEmpty()) {
            $add(self::LOW, 'unverified_actors', $unverified->pluck('name')->join(', '));
        }

        if ($lot->isUnderInvestigation()) {
            $add(self::MEDIUM, 'under_investigation', $lot->lot_number);
        }

        $order = [self::HIGH => 0, self::MEDIUM => 1, self::LOW => 2];

        return $warnings->sortBy(fn (array $warning) => $order[$warning['severity']])->values();
    }

    /**
     * Lots of the journey that present themselves as organic without a valid BIO certificate.
     *
     * @return list<string>
     */
    private function unprovenOrganicClaims(Journey $journey): array
    {
        $unproven = [];

        foreach ($journey->lots() as $lot) {
            $lot->loadMissing(['certifications', 'product.certifications']);

            $certifications = $lot->allCertifications();
            $bio = $certifications->where('type', CertificationType::BIO);

            $claims = $lot->production?->production_method === ProductionMethod::ORGANIC
                || $bio->isNotEmpty()
                // The final product's own wording counts as a claim too.
                || ($lot->is($journey->lot) && $this->mentions($lot, '/\bbio(logiques?)?\b/iu'));

            if ($claims && $bio->filter->isValid()->isEmpty()) {
                $unproven[] = $lot->product->name.' ('.$lot->lot_number.')';
            }
        }

        return $unproven;
    }

    /**
     * @param  Collection<int, Certification>  $certifications
     */
    private function claimsLocal(Lot $lot, Collection $certifications): bool
    {
        return $certifications->where('type', CertificationType::LOCAL)->isNotEmpty()
            || $this->mentions($lot, '/\b(local|locale|locaux|locales)\b/iu');
    }

    private function mentions(Lot $lot, string $pattern): bool
    {
        return (bool) preg_match($pattern, $lot->product->name.' '.$lot->product->description);
    }
}
