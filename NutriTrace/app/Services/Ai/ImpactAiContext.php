<?php

namespace App\Services\Ai;

use App\Models\EnvironmentalImpact;
use App\Models\Lot;
use App\Models\Review;
use App\Models\Transport;
use App\Services\Scoring\FootprintCalculator;

/**
 * Data handed to the local AI for the Impact environnemental - Avis et signalements module
 * (ai/nutritrace_ai/impact_check.py, advisor.py, moderation.py).
 */
class ImpactAiContext
{
    public function __construct(private readonly FootprintCalculator $calculator) {}

    /**
     * Figures a holder is about to declare, with what NutriTrace already computes from
     * the traced data of the lot: declaring less than that is a greenwashing signal.
     *
     * @param  array<string, array{value: float|string, source: string}>  $declared
     * @return array<string, mixed>
     */
    public function forDeclaration(Lot $lot, array $declared): array
    {
        $stages = $this->calculator->stages($lot);
        $co2 = array_filter([$stages['production_co2'], $stages['transformation_co2'], $stages['transport_co2']], fn ($v) => $v !== null);

        return [
            'id' => $lot->id,
            'lot' => $lot->lot_number,
            'product' => $lot->product->name,
            'category' => $lot->product->category?->name,
            'mass_kg' => $this->mass($lot),
            'calculated' => [
                'co2_kg' => $co2 === [] ? null : round(array_sum($co2), 3),
                'water_l' => $stages['water'] === null ? null : round($stages['water'], 3),
                'energy_kwh' => $stages['energy'] === null ? null : round($stages['energy'], 3),
            ],
            'declared' => collect($declared)
                ->map(fn (array $item) => ['value' => round((float) $item['value'], 3), 'source' => $item['source']])
                ->sortKeys()
                ->all(),
        ];
    }

    /**
     * A lot's footprint per kg, its CO₂ by stage and the scoring settings, so the advisor
     * recomputes the score exactly like FootprintCalculator::score().
     *
     * @return array<string, mixed>
     */
    public function forAdvice(Lot $lot): array
    {
        $impact = $lot->environmentalImpact ?? $this->calculator->refresh($lot);
        $stages = $this->calculator->stages($lot);
        $mass = $this->mass($lot);
        $perKg = fn (?float $value) => $value === null || $mass <= 0 ? null : round($value / $mass, 5);

        return [
            'id' => $lot->id,
            'lot' => $lot->lot_number,
            'product' => $lot->product->name,
            'category' => $lot->product->category?->name,
            'grade' => $impact->grade,
            'score' => $impact->score,
            'per_kg' => [
                'co2' => $perKg($impact->co2_kg),
                'water' => $perKg($impact->water_l),
                'energy' => $perKg($impact->energy_kwh),
                'food_miles' => (float) $impact->food_miles_km,
            ],
            'stages' => [
                'production_co2' => $stages['production_co2'],
                'transformation_co2' => $stages['transformation_co2'],
                'transport_co2' => $stages['transport_co2'],
                'packaging_co2' => $impact->packaging_co2_kg,
            ],
            'missing' => collect(EnvironmentalImpact::DECLARABLE)->filter(fn (string $indicator) => $impact->{$indicator} === null)->values()->all(),
            'transports' => $lot->transports->map(fn (Transport $transport) => [
                'mode' => $transport->transport_type->value,
                'distance_km' => $transport->distance_km,
            ])->all(),
            'config' => [
                'thresholds' => config('footprint.thresholds'),
                'weights' => config('footprint.weights'),
                'grades' => config('footprint.grades'),
                'factors' => config('footprint.emission_factors.transport'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forReview(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'product' => $review->product?->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function version(array $payload): string
    {
        unset($payload['id']);

        return hash('sha256', json_encode($payload));
    }

    private function mass(Lot $lot): float
    {
        return round($lot->initial_quantity * ($lot->unit->kilograms() ?? 1.0), 3);
    }
}
