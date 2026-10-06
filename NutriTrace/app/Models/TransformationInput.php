<?php

namespace App\Models;

use App\Observers\TransformationInputObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot between a transformation and one of its source lots.
 */
#[ObservedBy(TransformationInputObserver::class)]
class TransformationInput extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_used' => 'float',
        ];
    }

    public function transformation(): BelongsTo
    {
        return $this->belongsTo(Transformation::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
