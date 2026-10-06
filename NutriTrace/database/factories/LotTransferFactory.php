<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending hand-over of a farm lot to a transformer, without transport.
 * Real hand-overs go through LotDispatcher.
 *
 * @extends Factory<LotTransfer>
 */
class LotTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lot_id' => fn () => Production::factory()->create()->lot->id,
            'from_user_id' => fn (array $attributes) => Lot::find($attributes['lot_id'])->current_holder_id,
            'to_user_id' => User::factory()->transformer(),
            'quantity' => fn (array $attributes) => Lot::find($attributes['lot_id'])->quantity,
            'status' => TransferStatus::PENDING,
        ];
    }
}
