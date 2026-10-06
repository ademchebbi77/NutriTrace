<?php

namespace App\Services;

use App\Models\Lot;
use App\Services\Scoring\FootprintCalculator;
use App\Services\Scoring\GreenwashingWarnings;
use App\Services\Scoring\LocalRule;
use App\Services\Scoring\TrustScoreCalculator;
use App\Services\Traceability\ChainVerifier;
use App\Services\Traceability\JourneyBuilder;

class LotInsightsBuilder
{
    public function __construct(
        private readonly JourneyBuilder $journeys,
        private readonly ChainVerifier $verifier,
        private readonly TrustScoreCalculator $trust,
        private readonly GreenwashingWarnings $warnings,
        private readonly LocalRule $localRule,
        private readonly FootprintCalculator $footprint,
    ) {}

    public function for(Lot $lot): LotInsights
    {
        $lot->loadMissing(['product.category', 'product.certifications', 'product.creator.organization', 'certifications', 'environmentalImpact']);

        $journey = $this->journeys->build($lot);

        return new LotInsights(
            lot: $lot,
            journey: $journey,
            chain: $this->verifier->verifyJourney($journey),
            trust: $this->trust->calculate($lot, $journey),
            warnings: $this->warnings->for($lot, $journey),
            local: $this->localRule->evaluate($journey),
            impact: $lot->environmentalImpact,
            stages: $this->footprint->stages($lot),
            certifications: $lot->allCertifications(),
            underInvestigation: $lot->isUnderInvestigation(),
        );
    }
}
