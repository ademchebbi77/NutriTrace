<?php

namespace App\Services;

use App\Models\Certification;
use App\Models\EnvironmentalImpact;
use App\Models\Lot;
use App\Services\Scoring\TrustScore;
use App\Services\Traceability\ChainStatus;
use App\Services\Traceability\Journey;
use Illuminate\Support\Collection;

/**
 * Everything the public needs to judge a lot, computed once and shared by the
 * traceability page, the comparison page and the JSON API.
 */
final readonly class LotInsights
{
    /**
     * @param  Collection<int, Certification>  $certifications  Every certification of the lot and its product.
     * @param  Collection<int, array{severity: string, code: string, message: string, related: ?string}>  $warnings
     * @param  array{is_local: ?bool, distance_km: ?float, radius_km: float}  $local
     * @param  array<string, ?float>  $stages
     */
    public function __construct(
        public Lot $lot,
        public Journey $journey,
        public ChainStatus $chain,
        public TrustScore $trust,
        public Collection $warnings,
        public array $local,
        public ?EnvironmentalImpact $impact,
        public array $stages,
        public Collection $certifications,
        public bool $underInvestigation,
    ) {}

    /**
     * @return Collection<int, Certification>
     */
    public function validCertifications(): Collection
    {
        return $this->certifications->filter->isValid()->values();
    }

    /**
     * CO2 per kilogram (or litre) of product, for fair comparisons between lots.
     */
    public function co2PerKg(): ?float
    {
        $mass = $this->lot->initial_quantity * ($this->lot->unit->kilograms() ?? 1.0);

        return $this->impact?->co2_kg !== null && $mass > 0 ? round($this->impact->co2_kg / $mass, 3) : null;
    }
}
