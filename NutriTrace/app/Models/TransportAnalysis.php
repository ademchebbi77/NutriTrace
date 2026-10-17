<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anomaly analysis of a transport: label and reasons from the scikit-learn
 * model, explanation written by the local LLM.
 */
class TransportAnalysis extends Model
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
            'reasons' => 'array',
        ];
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(Transport::class);
    }

    public function labelText(): string
    {
        return __('ai.labels.'.$this->label);
    }
}
