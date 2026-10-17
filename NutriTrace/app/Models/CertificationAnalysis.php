<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Risk of a certificate according to the local AI: level and reasons from the
 * scikit-learn model, explanation for the admin written by the LLM.
 */
class CertificationAnalysis extends Model
{
    public const LEVEL_COLORS = ['faible' => 'success', 'moyen' => 'warning', 'eleve' => 'danger'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risk' => 'float',
            'confidence' => 'float',
            'reasons' => 'array',
            'document_readable' => 'boolean',
        ];
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    public function levelText(): string
    {
        return __('ai.certification.levels.'.$this->level);
    }

    public function color(): string
    {
        return self::LEVEL_COLORS[$this->level] ?? 'secondary';
    }

    public function isSuspicious(): bool
    {
        return $this->level !== 'faible';
    }
}
