<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Local AI check of a transformation: plausibility of the output for the inputs, of the
 * energy and water declared, freshness of the sources. Made for the transformer before
 * saving (then attached to the transformation) or for the admin.
 */
class TransformationCheck extends Model
{
    public const TRANSFORMER = 'transformer';

    public const ADMIN = 'admin';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_anomaly' => 'boolean',
            'risk' => 'float',
            'confidence' => 'float',
            'expected' => 'array',
            'reasons' => 'array',
        ];
    }

    public function transformation(): BelongsTo
    {
        return $this->belongsTo(Transformation::class);
    }

    public function labelText(): string
    {
        return __('ai.transformation.labels.'.$this->label);
    }

    public function color(): string
    {
        return match (true) {
            ! $this->is_anomaly => 'success',
            $this->label === 'resources_missing' => 'info',
            $this->risk >= 0.6 => 'danger',
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
            'risk' => $this->risk,
            'expected' => $this->expected,
            'reasons' => $this->reasons,
            'explanation' => $this->explanation,
        ];
    }
}
