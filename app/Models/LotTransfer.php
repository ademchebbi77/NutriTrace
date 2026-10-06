<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Database\Factories\LotTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hand-over of a lot to a transformer. The holder changes only when the receiver accepts.
 * (Hand-overs to a distributor are Distribution records.)
 */
#[Fillable(['note'])]
class LotTransfer extends Model
{
    /** @use HasFactory<LotTransferFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TransferStatus::PENDING->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'quantity' => 'float',
            'responded_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(Transport::class);
    }

    public function isPending(): bool
    {
        return $this->status === TransferStatus::PENDING;
    }
}
