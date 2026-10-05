<?php

namespace App\Models;

use App\Enums\CertificationStatus;
use App\Enums\CertificationType;
use App\Observers\CertificationObserver;
use Database\Factories\CertificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

// Status and review fields are set by the review service and the expiry command only.
#[ObservedBy(CertificationObserver::class)]
#[Fillable(['name', 'type', 'issuing_organization', 'certificate_number', 'issue_date', 'expiration_date'])]
class Certification extends Model
{
    /** @use HasFactory<CertificationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => CertificationStatus::PENDING->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CertificationType::class,
            'status' => CertificationStatus::class,
            'issue_date' => 'date',
            'expiration_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The Product or Lot the certificate covers.
     */
    public function certifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->unless($user->isAdmin(), fn (Builder $q) => $q->where('owner_id', $user->id));
    }

    /**
     * Verified and not past its expiration date: the only state shown as valid to consumers.
     */
    public function scopeValid(Builder $query): void
    {
        $query->where('status', CertificationStatus::VERIFIED)
            ->where(fn (Builder $q) => $q->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', today()));
    }

    public function isValid(): bool
    {
        return $this->status === CertificationStatus::VERIFIED && ! $this->isPastExpiration();
    }

    public function isPastExpiration(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->lt(today());
    }

    public function hasProof(): bool
    {
        return $this->document_path !== null && filled($this->certificate_number);
    }

    /**
     * Short French label of what the certificate covers.
     */
    public function targetLabel(): string
    {
        return match (true) {
            $this->certifiable instanceof Lot => __('certifications.fields.lot', ['name' => $this->certifiable->lot_number]),
            $this->certifiable instanceof Product => __('certifications.fields.product', ['name' => $this->certifiable->name]),
            default => '—',
        };
    }

    /**
     * Lots whose scores depend on this certification.
     *
     * @return Collection<int, Lot>
     */
    public function affectedLots(): Collection
    {
        return match (true) {
            $this->certifiable instanceof Lot => collect([$this->certifiable]),
            $this->certifiable instanceof Product => $this->certifiable->lots()->get(),
            default => collect(),
        };
    }
}
