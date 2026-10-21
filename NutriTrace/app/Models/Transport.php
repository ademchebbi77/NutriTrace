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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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
     * Set before cancelling, so the journal tells a withdrawal by the shipper
     * apart from a refusal by the receiver (not stored).
     */
    public bool $cancelledByShipper = false;

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
            'handover_quantity' => 'float',
            'handed_over_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every transport has an electronic consignment note with a public QR code.
        static::creating(function (Transport $transport) {
            $transport->consignment_token ??= Str::random(40);
            $transport->consignment_number ??= 'LV-'.now()->format('y').'-'.strtoupper(Str::random(6));
        });
    }

    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function consignmentUrl(): string
    {
        return route('consignment.show', $this->consignment_token);
    }

    public function isHandedOver(): bool
    {
        return $this->handed_over_at !== null;
    }

    /** Received minus sent (negative: missing goods); null before the hand-over. */
    public function handoverGap(): ?float
    {
        return $this->isHandedOver() ? round($this->handover_quantity - $this->quantity_transported, 3) : null;
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

    /**
     * Hand-over to a transformer carried by this transport, if any.
     */
    public function transfer(): HasOne
    {
        return $this->hasOne(LotTransfer::class);
    }

    /**
     * Hand-over to a distributor carried by this transport, if any.
     */
    public function distribution(): HasOne
    {
        return $this->hasOne(Distribution::class);
    }

    /**
     * AI anomaly analyses, the most recent first.
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(TransportAnalysis::class)->latest('id');
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

    /**
     * kg CO2e of the trip (distance x tonnes x factor of the mode), or null when unknown.
     */
    public function co2Kg(): ?float
    {
        $mass = $this->massKg();

        return $mass !== null && $this->distance_km !== null
            ? $this->distance_km * ($mass / 1000) * config('footprint.emission_factors.transport.'.$this->transport_type->value)
            : null;
    }
}
