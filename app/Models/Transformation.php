<?php

namespace App\Models;

use App\Enums\Unit;
use App\Observers\TransformationObserver;
use Database\Factories\TransformationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(TransformationObserver::class)]
#[Fillable(['transformation_date', 'process_description', 'energy_used_kwh', 'water_used_l'])]
class Transformation extends Model
{
    /** @use HasFactory<TransformationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transformation_date' => 'date',
            'input_quantity' => 'float',
            'output_quantity' => 'float',
            'input_unit' => Unit::class,
            'energy_used_kwh' => 'float',
            'water_used_l' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function transformer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transformer_id');
    }

    public function outputLot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'output_lot_id');
    }

    /**
     * Source lots with the quantity taken from each.
     */
    public function inputs(): HasMany
    {
        return $this->hasMany(TransformationInput::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->unless($user->isAdmin(), fn (Builder $q) => $q->where('transformer_id', $user->id));
    }

    /**
     * Output over input when both use the same unit family, e.g. 900 L from 5 000 kg = 18 %.
     */
    public function yieldPercent(): ?float
    {
        return $this->input_quantity > 0 ? round($this->output_quantity / $this->input_quantity * 100, 1) : null;
    }
}
