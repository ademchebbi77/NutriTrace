<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Date limit of a lot judged by the local AI against the shelf life expected for its
 * product, with a suggested date.
 */
class LotExpiryCheck extends Model
{
    public const COLORS = ['plausible' => 'success', 'too_long' => 'danger', 'too_short' => 'warning', 'missing' => 'secondary'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suggested_date' => 'date',
            'reasons' => 'array',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function labelText(): string
    {
        return __('ai.expiry.labels.'.$this->label);
    }

    public function color(): string
    {
        return self::COLORS[$this->label] ?? 'secondary';
    }

    public function isSuspicious(): bool
    {
        return $this->label !== 'plausible';
    }
}
