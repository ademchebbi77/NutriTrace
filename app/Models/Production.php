<?php

namespace App\Models;

use App\Enums\LotStatus;
use App\Enums\ProductionMethod;
use App\Enums\Unit;
use App\Observers\ProductionObserver;
use Database\Factories\ProductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(ProductionObserver::class)]
#[Fillable([
    'product_id', 'location_address', 'location_city', 'latitude', 'longitude',
    'production_date', 'quantity', 'unit', 'production_method', 'resources_used',
])]
class Production extends Model
{
    /** @use HasFactory<ProductionFactory> */
    use HasFactory;

    /**
     * Numeric keys accepted in resources_used.
     */
    public const RESOURCE_KEYS = ['water_l', 'energy_kwh', 'fertilizer_kg', 'pesticide_kg'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'quantity' => 'float',
            'latitude' => 'float',
            'longitude' => 'float',
            'unit' => Unit::class,
            'production_method' => ProductionMethod::class,
            'resources_used' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    /**
     * The lot created automatically with this production.
     */
    public function lot(): HasOne
    {
        return $this->hasOne(Lot::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->unless($user->isAdmin(), fn (Builder $q) => $q->where('producer_id', $user->id));
    }

    /**
     * A production can be corrected or removed only while its lot is untouched:
     * still held by the producer, never transferred nor consumed.
     */
    public function isEditable(): bool
    {
        $lot = $this->lot;

        return $lot !== null
            && $lot->status === LotStatus::CREATED
            && $lot->current_holder_id === $this->producer_id
            && $lot->quantity === $lot->initial_quantity;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
