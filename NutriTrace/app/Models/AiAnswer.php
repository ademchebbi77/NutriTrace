<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached answer of the local AI assistant to a question about a lot.
 */
class AiAnswer extends Model
{
    protected $guarded = ['id'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * Same question whatever the case, spacing or final punctuation.
     */
    public static function hashQuestion(string $question): string
    {
        $normalized = rtrim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($question))), ' ?!.');

        return hash('sha256', $normalized);
    }
}
