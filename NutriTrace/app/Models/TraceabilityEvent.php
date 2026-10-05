<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Append-only journal entry of a lot. Created only by TraceabilityRecorder;
 * a correction is a new event, never an edit.
 */
class TraceabilityEvent extends Model
{
    public const UPDATED_AT = null;

    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Traceability events are append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new LogicException('Traceability events are append-only and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'occurred_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'metadata' => 'array',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Public name of the actor: the organization, never the private contact.
     */
    public function actorName(): ?string
    {
        return $this->actor?->organization?->name;
    }
}
