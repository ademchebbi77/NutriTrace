<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Advice of the local AI before a distributor accepts a lot: accept, accept with
 * reservations or refuse, with the reasons and an explanation.
 */
class ReceptionCheck extends Model
{
    public const DECISION_COLORS = ['accepter' => 'success', 'reserves' => 'warning', 'refuser' => 'danger'];

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'reasons' => 'array',
        ];
    }

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    public function decisionText(): string
    {
        return __('ai.reception.decisions.'.$this->decision);
    }

    public function color(): string
    {
        return self::DECISION_COLORS[$this->decision] ?? 'secondary';
    }
}
