<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Observers\ReportObserver;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

#[ObservedBy(ReportObserver::class)]
#[Fillable(['type', 'description'])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ReportStatus::PENDING->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'status' => ReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Product, Lot, Certification or EnvironmentalImpact being reported.
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [ReportStatus::PENDING, ReportStatus::UNDER_REVIEW]);
    }

    /**
     * Reports about a lot, its product, its environmental claim or any of their certifications.
     */
    public function scopeConcerningLot(Builder $query, Lot $lot): void
    {
        $certificationIds = Certification::query()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $c) => $c->where('certifiable_type', $lot->getMorphClass())->where('certifiable_id', $lot->id))
                ->orWhere(fn (Builder $c) => $c->where('certifiable_type', (new Product)->getMorphClass())->where('certifiable_id', $lot->product_id)))
            ->select('id');

        $impactIds = EnvironmentalImpact::query()->where('lot_id', $lot->id)->select('id');

        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $r) => $r->where('reportable_type', $lot->getMorphClass())->where('reportable_id', $lot->id))
            ->orWhere(fn (Builder $r) => $r->where('reportable_type', (new Product)->getMorphClass())->where('reportable_id', $lot->product_id))
            ->orWhere(fn (Builder $r) => $r->where('reportable_type', (new Certification)->getMorphClass())->whereIn('reportable_id', $certificationIds))
            ->orWhere(fn (Builder $r) => $r->where('reportable_type', (new EnvironmentalImpact)->getMorphClass())->whereIn('reportable_id', $impactIds)));
    }

    /**
     * Lots whose transparency score this report weighs on.
     *
     * @return Collection<int, Lot>
     */
    public function affectedLots(): Collection
    {
        $target = $this->reportable;

        return match (true) {
            $target instanceof Lot => collect([$target]),
            $target instanceof Product => $target->lots()->get(),
            $target instanceof Certification => $target->affectedLots(),
            $target instanceof EnvironmentalImpact => collect([$target->lot])->filter(),
            default => collect(),
        };
    }

    /**
     * Short French label of what is reported, for lists.
     */
    public function targetLabel(): string
    {
        $target = $this->reportable;

        return match (true) {
            $target instanceof Lot => __('reports.targets.lot', ['name' => $target->lot_number]),
            $target instanceof Product => __('reports.targets.product', ['name' => $target->name]),
            $target instanceof Certification => __('reports.targets.certification', ['name' => $target->name]),
            $target instanceof EnvironmentalImpact => __('reports.targets.impact', ['name' => $target->lot?->lot_number]),
            default => __('reports.targets.deleted'),
        };
    }
}
