<?php

namespace App\Services\Scoring;

use App\Enums\DataSource;
use App\Enums\TransportStatus;
use App\Models\EnvironmentalImpact;
use App\Models\Lot;
use App\Models\User;

/**
 * Environmental footprint of a lot.
 *
 * The footprint of a lot is the sum of what happened to it (production or
 * transformation, then its transports) plus its share of the footprint of every
 * source lot: a transformation that used 40 % of a source lot inherits 40 % of
 * that lot's footprint. Factors and thresholds come from config/footprint.php.
 */
class FootprintCalculator
{
    /**
     * Compute and store the footprint of a lot, keeping the values declared by its holder.
     */
    public function refresh(Lot $lot): EnvironmentalImpact
    {
        $impact = EnvironmentalImpact::firstOrNew(['lot_id' => $lot->id]);
        $declared = $impact->declared ?? [];
        $stages = $this->stages($lot);

        $values = [];

        // Water and energy: sums of the figures given by the actors, unless the holder declared a total.
        foreach (['water_l' => 'water', 'energy_kwh' => 'energy'] as $indicator => $key) {
            $values[$indicator] = isset($declared[$indicator])
                ? [(float) $declared[$indicator]['value'], DataSource::from($declared[$indicator]['source'])]
                : [$stages[$key], $stages[$key] === null ? null : DataSource::PROVIDED];
        }

        // Waste and packaging are only known when declared.
        foreach (['waste_kg', 'packaging_co2_kg'] as $indicator) {
            $values[$indicator] = isset($declared[$indicator])
                ? [(float) $declared[$indicator]['value'], DataSource::from($declared[$indicator]['source'])]
                : [null, null];
        }

        $transportCo2 = $stages['transport_co2'];
        $values['transport_co2_kg'] = [$transportCo2, $transportCo2 === null ? null : DataSource::CALCULATED];

        if (isset($declared['co2_kg'])) {
            $values['co2_kg'] = [(float) $declared['co2_kg']['value'], DataSource::from($declared['co2_kg']['source'])];
        } else {
            $parts = array_filter(
                [$stages['production_co2'], $stages['transformation_co2'], $transportCo2, $values['packaging_co2_kg'][0]],
                fn ($part) => $part !== null,
            );
            $values['co2_kg'] = $parts === [] ? [null, null] : [array_sum($parts), DataSource::CALCULATED];
        }

        foreach ($values as $indicator => [$value, $source]) {
            $impact->{$indicator} = $value === null ? null : round($value, 3);
            $impact->{$indicator.'_source'} = $source;
        }

        $impact->food_miles_km = round($stages['food_miles'], 2);
        $impact->score = $this->score($lot, $impact);
        $impact->grade = $this->grade($impact->score);
        $impact->calculated_at = now();
        $impact->save();

        $lot->setRelation('environmentalImpact', $impact);

        return $impact;
    }

    /**
     * Store the values declared by the holder (measured or provided), then recompute.
     *
     * @param  array<string, array{value: float|string, source: string}>  $declared
     */
    public function declare(Lot $lot, User $user, array $declared): EnvironmentalImpact
    {
        $impact = EnvironmentalImpact::firstOrNew(['lot_id' => $lot->id]);
        $impact->declared = $declared ?: null;
        $impact->declared_by = $user->id;
        $impact->save();

        return $this->refresh($lot);
    }

    /**
     * Footprint of the whole lot split by stage, upstream lots included.
     * A null value means that no actor gave any figure for it.
     *
     * @return array{production_co2: ?float, transformation_co2: ?float, transport_co2: ?float, water: ?float, energy: ?float, food_miles: float}
     */
    public function stages(Lot $lot, array &$visiting = []): array
    {
        $stages = ['production_co2' => null, 'transformation_co2' => null, 'transport_co2' => null, 'water' => null, 'energy' => null, 'food_miles' => 0.0];

        if (isset($visiting[$lot->id])) {
            return $stages;
        }

        $visiting[$lot->id] = true;
        $factors = config('footprint.emission_factors');

        if ($production = $lot->production) {
            $resources = $production->resources_used ?? [];
            $stages['water'] = $this->number($resources['water_l'] ?? null);
            $stages['energy'] = $this->number($resources['energy_kwh'] ?? null);

            $stages['production_co2'] = $this->sumOrNull([
                $this->times($stages['energy'], $factors['electricity']),
                $this->times($this->number($resources['fertilizer_kg'] ?? null), $factors['fertilizer']),
                $this->times($this->number($resources['pesticide_kg'] ?? null), $factors['pesticide']),
            ]);
        }

        if ($transformation = $lot->transformation) {
            $stages['water'] = $this->sumOrNull([$stages['water'], $transformation->water_used_l]);
            $stages['energy'] = $this->sumOrNull([$stages['energy'], $transformation->energy_used_kwh]);
            $stages['transformation_co2'] = $this->times($transformation->energy_used_kwh, $factors['electricity']);

            $transformation->loadMissing('inputs.lot');
            $inputTotal = max($transformation->inputs->sum('quantity_used'), 0.0001);

            foreach ($transformation->inputs as $input) {
                $source = $input->lot;
                $upstream = $this->stages($source, $visiting);
                $share = $source->initial_quantity > 0 ? min(1.0, $input->quantity_used / $source->initial_quantity) : 0.0;

                foreach (['production_co2', 'transformation_co2', 'transport_co2', 'water', 'energy'] as $key) {
                    $stages[$key] = $this->sumOrNull([$stages[$key], $this->times($upstream[$key], $share)]);
                }

                // Distance already travelled by the ingredients, weighted by the quantity of each.
                $stages['food_miles'] += $upstream['food_miles'] * ($input->quantity_used / $inputTotal);
            }
        }

        foreach ($lot->transports as $transport) {
            if ($transport->status === TransportStatus::CANCELLED || $transport->distance_km === null) {
                continue;
            }

            $stages['food_miles'] += $transport->distance_km;

            $massKg = $transport->massKg();

            if ($massKg !== null) {
                $factor = $factors['transport'][$transport->transport_type->value] ?? 0.0;
                $stages['transport_co2'] = $this->sumOrNull([
                    $stages['transport_co2'],
                    $transport->distance_km * ($massKg / 1000) * $factor,
                ]);
            }
        }

        unset($visiting[$lot->id]);

        return $stages;
    }

    /**
     * Score 0-100 from the indicators that are known, per kilogram of product.
     */
    public function score(Lot $lot, EnvironmentalImpact $impact): ?int
    {
        $massKg = $lot->initial_quantity * ($lot->unit->kilograms() ?? 1.0);

        if ($massKg <= 0) {
            return null;
        }

        $thresholds = config('footprint.thresholds');
        $weights = config('footprint.weights');

        $indicators = [
            'co2' => [$impact->co2_kg === null ? null : $impact->co2_kg / $massKg, $thresholds['co2_per_kg']],
            'water' => [$impact->water_l === null ? null : $impact->water_l / $massKg, $thresholds['water_per_kg']],
            'energy' => [$impact->energy_kwh === null ? null : $impact->energy_kwh / $massKg, $thresholds['energy_per_kg']],
            // Food miles only count once the lot has actually travelled.
            'food_miles' => [$impact->food_miles_km > 0 ? $impact->food_miles_km : null, $thresholds['food_miles']],
        ];

        $total = 0.0;
        $weightSum = 0.0;

        foreach ($indicators as $key => [$value, $scale]) {
            if ($value === null) {
                continue;
            }

            $total += $this->subScore($value, (float) $scale['best'], (float) $scale['worst']) * $weights[$key];
            $weightSum += $weights[$key];
        }

        // Without any quantified impact (CO2, water or energy) there is nothing to grade.
        if ($weightSum <= 0 || ($impact->co2_kg === null && $impact->water_l === null && $impact->energy_kwh === null)) {
            return null;
        }

        return (int) round($total / $weightSum);
    }

    public function grade(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach (config('footprint.grades') as $grade => $minimum) {
            if ($score >= $minimum) {
                return $grade;
            }
        }

        return 'E';
    }

    /**
     * 100 at "best" or below, 0 at "worst" or above, linear in between.
     */
    private function subScore(float $value, float $best, float $worst): float
    {
        if ($worst <= $best) {
            return $value <= $best ? 100.0 : 0.0;
        }

        return max(0.0, min(100.0, ($worst - $value) / ($worst - $best) * 100));
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function times(?float $value, float $factor): ?float
    {
        return $value === null ? null : $value * $factor;
    }

    /**
     * Sum of the known values, or null when none is known.
     *
     * @param  list<?float>  $values
     */
    private function sumOrNull(array $values): ?float
    {
        $known = array_filter($values, fn ($value) => $value !== null);

        return $known === [] ? null : array_sum($known);
    }
}
