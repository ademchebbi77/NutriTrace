<?php

namespace App\Models;

use App\Enums\LotStatus;
use App\Enums\ReportStatus;
use App\Enums\Unit;
use App\Enums\UserRole;
use App\Services\LotNumberGenerator;
use Database\Factories\LotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// Status, holder, quantities and identifiers change only through services and observers.
#[Fillable(['expiration_date'])]
class Lot extends Model
{
    /** @use HasFactory<LotFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => LotStatus::CREATED->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (Lot $lot) {
            $lot->lot_number ??= app(LotNumberGenerator::class)->next($lot->production_date?->year ?? now()->year);
            $lot->public_token ??= Str::lower(Str::random(32));
            $lot->initial_quantity ??= $lot->quantity;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'expiration_date' => 'date',
            'initial_quantity' => 'float',
            'quantity' => 'float',
            'unit' => Unit::class,
            'status' => LotStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_holder_id');
    }

    /**
     * The transformation this lot came out of (null for farm lots).
     */
    public function transformation(): HasOne
    {
        return $this->hasOne(Transformation::class, 'output_lot_id');
    }

    /**
     * Transformations that consumed part of this lot.
     */
    public function transformationInputs(): HasMany
    {
        return $this->hasMany(TransformationInput::class);
    }

    public function transports(): HasMany
    {
        return $this->hasMany(Transport::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(LotTransfer::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TraceabilityEvent::class);
    }

    public function environmentalImpact(): HasOne
    {
        return $this->hasOne(EnvironmentalImpact::class);
    }

    public function certifications(): MorphMany
    {
        return $this->morphMany(Certification::class, 'certifiable');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Admins see every lot; an actor sees every lot it produced, held, sent,
     * received, transformed or distributed.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->unless($user->isAdmin(), fn (Builder $q) => $q->where(
            fn (Builder $own) => $own
                ->where('current_holder_id', $user->id)
                ->orWhereHas('production', fn (Builder $p) => $p->where('producer_id', $user->id))
                ->orWhereHas('transformation', fn (Builder $t) => $t->where('transformer_id', $user->id))
                ->orWhereHas('transformationInputs.transformation', fn (Builder $t) => $t->where('transformer_id', $user->id))
                ->orWhereHas('transfers', fn (Builder $t) => $t->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id))
                ->orWhereHas('distributions', fn (Builder $d) => $d->where('sender_id', $user->id)->orWhere('distributor_id', $user->id))
        ));
    }

    public function scopeHeldBy(Builder $query, User $user): void
    {
        $query->where('current_holder_id', $user->id);
    }

    /**
     * Lots a consumer may look up: every lot of a product that is not a draft.
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->whereHas('product', fn (Builder $p) => $p->publiclyVisible());
    }

    public function isHeldBy(User $user): bool
    {
        return $this->current_holder_id === $user->id;
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    public function isExpired(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->isPast();
    }

    /**
     * A lot at rest with its holder and with stock left can be sent or transformed.
     */
    public function isAvailable(): bool
    {
        return $this->quantity > 0
            && in_array($this->status, [LotStatus::CREATED, LotStatus::IN_TRANSFORMATION], true);
    }

    /**
     * Status of a lot at rest with the given holder: a transformer holding a lot
     * it did not make itself is keeping it for transformation.
     */
    public function restingStatusFor(User $holder): LotStatus
    {
        $madeByHolder = $this->production?->producer_id === $holder->id
            || $this->transformation?->transformer_id === $holder->id;

        return $holder->role === UserRole::TRANSFORMATEUR && ! $madeByHolder
            ? LotStatus::IN_TRANSFORMATION
            : LotStatus::CREATED;
    }

    /**
     * Certifications of the lot and of its product that consumers can rely on.
     *
     * @return Collection<int, Certification>
     */
    public function validCertifications(): Collection
    {
        return $this->allCertifications()->filter->isValid()->values();
    }

    /**
     * Every certification attached to the lot or to its product, whatever its status.
     *
     * @return Collection<int, Certification>
     */
    public function allCertifications(): Collection
    {
        return $this->certifications->concat($this->product->certifications)->values();
    }

    /**
     * True when a report about this lot or its product is being investigated.
     */
    public function isUnderInvestigation(): bool
    {
        return Report::query()
            ->where('status', ReportStatus::UNDER_REVIEW)
            ->concerningLot($this)
            ->exists();
    }

    public function publicUrl(): string
    {
        return route('trace.show', $this->public_token);
    }

    /**
     * Quantity with its unit, e.g. "5 000 kg".
     */
    public function formattedQuantity(?float $quantity = null): string
    {
        return format_quantity($quantity ?? $this->quantity, $this->unit);
    }
}
