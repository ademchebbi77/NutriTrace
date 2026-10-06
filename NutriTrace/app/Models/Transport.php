<?php

namespace App\Models;

use App\Enums\TransportStatus;
use App\Enums\TransportType;
use App\Enums\Unit;
use App\Observers\TransportObserver;
use Database\Factories\TransportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(TransportObserver::class)]
#[Fillable(['transport_type', 'distance_km', 'departure_date'])]
class Transport extends Model
{
    /** @use HasFactory<TransportFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TransportStatus::IN_TRANSIT->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transport_type' => TransportType::class,
            'status' => TransportStatus::class,
            'unit' => Unit::class,
            'departure_date' => 'datetime',
            'arrival_date' => 'datetime',
            'distance_km' => 'float',
            'quantity_transported' => 'float',
            'origin_latitude' => 'float',
            'origin_longitude' => 'float',
            'destination_latitude' => 'float',
            'destination_longitude' => 'float',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->unless($user->isAdmin(), fn (Builder $q) => $q->where(
            fn (Builder $own) => $own->where('shipper_id', $user->id)->orWhere('recipient_id', $user->id)
        ));
    }

    public function hasCoordinates(): bool
    {
        return $this->origin_latitude !== null && $this->origin_longitude !== null
            && $this->destination_latitude !== null && $this->destination_longitude !== null;
    }

    /**
     * Mass moved in kilograms, or null when the unit has no known mass.
     */
    public function massKg(): ?float
    {
        $perUnit = $this->unit->kilograms();

        return $perUnit === null ? null : $this->quantity_transported * $perUnit;
    }
}
