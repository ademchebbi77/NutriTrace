<?php

namespace App\Models;

use App\Enums\DistributionStatus;
use App\Observers\DistributionObserver;
use Carbon\CarbonInterface;
use Database\Factories\DistributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hand-over of a lot to a distributor, then its life in the store.
 */
#[ObservedBy(DistributionObserver::class)]
#[Fillable(['destination'])]
class Distribution extends Model
{
    /** @use HasFactory<DistributionFactory> */
    use HasFactory;

    /**
     * Moment of the status change being saved, read by DistributionObserver
     * to date the matching event. Not persisted; defaults to now.
     */
    public ?CarbonInterface $eventAt = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => DistributionStatus::PENDING->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DistributionStatus::class,
            'quantity' => 'float',
            'distribution_date' => 'date',
            'reception_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributor_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(Transport::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->unless($user->isAdmin(), fn (Builder $q) => $q->where(
            fn (Builder $own) => $own->where('distributor_id', $user->id)->orWhere('sender_id', $user->id)
        ));
    }

    public function isPending(): bool
    {
        return $this->status === DistributionStatus::PENDING;
    }

    /**
     * The distributor holds the goods (received or already on the shelves).
     */
    public function isAccepted(): bool
    {
        return in_array($this->status, [DistributionStatus::RECEIVED, DistributionStatus::IN_STORE], true);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
