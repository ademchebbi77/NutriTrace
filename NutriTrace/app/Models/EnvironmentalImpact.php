<?php

namespace App\Models;

use App\Enums\DataSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Cached footprint of a lot. Written by FootprintCalculator; the "declared"
 * values are entered by the holder and fed back into the calculation.
 */
class EnvironmentalImpact extends Model
{
    /**
     * Indicators that carry a value and a source.
     */
    public const INDICATORS = ['co2_kg', 'water_l', 'energy_kwh', 'waste_kg', 'transport_co2_kg', 'packaging_co2_kg'];

    /**
     * Indicators the holder may declare by hand.
     */
    public const DECLARABLE = ['co2_kg', 'water_l', 'energy_kwh', 'waste_kg', 'packaging_co2_kg'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'co2_kg' => 'float',
            'water_l' => 'float',
            'energy_kwh' => 'float',
            'waste_kg' => 'float',
            'transport_co2_kg' => 'float',
            'packaging_co2_kg' => 'float',
            'food_miles_km' => 'float',
            'co2_kg_source' => DataSource::class,
            'water_l_source' => DataSource::class,
            'energy_kwh_source' => DataSource::class,
            'waste_kg_source' => DataSource::class,
            'transport_co2_kg_source' => DataSource::class,
            'packaging_co2_kg_source' => DataSource::class,
            'declared' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function source(string $indicator): ?DataSource
    {
        return $this->{$indicator.'_source'};
    }

    /**
     * True when the footprint rests on figures, not only on claims.
     */
    public function isQuantified(): bool
    {
        return $this->co2_kg !== null && $this->score !== null;
    }

    public function gradeColor(): string
    {
        return match ($this->grade) {
            'A' => 'success',
            'B' => 'info',
            'C' => 'warning',
            'D' => 'danger',
            'E' => 'dark',
            default => 'secondary',
        };
    }
}
