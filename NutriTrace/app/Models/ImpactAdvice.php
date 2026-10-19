<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Advice of the local AI to improve a lot's environmental grade: quantified levers
 * (computed with the scoring rules of NutriTrace) and an explanation by the LLM.
 */
class ImpactAdvice extends Model
{
    protected $table = 'impact_advice';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['levers' => 'array'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
