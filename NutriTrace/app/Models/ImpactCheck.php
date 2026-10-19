<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Local AI check of the environmental figures declared for a lot: one verdict per
 * indicator (plausible, under-declared, too high, too low, impossible).
 */
class ImpactCheck extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_anomaly' => 'boolean',
            'confidence' => 'float',
            'findings' => 'array',
            'reasons' => 'array',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function labelText(): string
    {
        return __('ai.impact.labels.'.$this->label);
    }

    public function color(): string
    {
        return match ($this->label) {
            'ok' => 'success',
            'impossible', 'under_declared' => 'danger',
            default => 'warning',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toResult(): array
    {
        return [
            'id' => $this->id,
            'is_anomaly' => $this->is_anomaly,
            'label' => $this->label,
            'label_text' => $this->labelText(),
            'color' => $this->color(),
            'reasons' => $this->reasons,
            'explanation' => $this->explanation,
        ];
    }
}
