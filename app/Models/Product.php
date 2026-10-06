<?php

namespace App\Models;

use App\Enums\CertificationType;
use App\Enums\LotStatus;
use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'name', 'description', 'origin', 'status', 'barcode'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProductStatus::DRAFT->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * Admins see every product, other actors only their own.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->unless($user->isAdmin(), fn (Builder $q) => $q->where('created_by', $user->id));
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

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::PUBLISHED);
    }

    /**
     * Published products are listed; archived ones stay traceable because their lots may still be on sale.
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->whereIn('status', [ProductStatus::PUBLISHED, ProductStatus::ARCHIVED]);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status !== ProductStatus::DRAFT;
    }

    /**
     * Lot shown on catalog cards: the most recent one that has an environmental grade,
     * otherwise the most recent one. Uses the loaded "lots" relation.
     */
    public function showcaseLot(): ?Lot
    {
        $lots = $this->lots->sortByDesc('production_date');
        $graded = $lots->filter(fn (Lot $lot) => $lot->environmentalImpact?->grade !== null);

        // A lot that reached a distributor tells the whole story, so it comes first.
        $reachedStore = [LotStatus::DISTRIBUTED, LotStatus::IN_STORE, LotStatus::SOLD_OUT];

        return $graded->first(fn (Lot $lot) => in_array($lot->status, $reachedStore, true))
            ?? $graded->first()
            ?? $lots->first();
    }

    /**
     * Types of the certifications consumers can rely on, for the product or any of its lots.
     * Uses the loaded "certifications" and "lots.certifications" relations.
     *
     * @return Collection<int, CertificationType>
     */
    public function validCertificationTypes()
    {
        return $this->certifications
            ->concat($this->lots->flatMap->certifications)
            ->filter->isValid()
            ->pluck('type')
            ->unique()
            ->values();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->created_by === $user->id;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
