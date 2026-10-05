<?php

namespace Database\Factories;

use App\Enums\DistributionStatus;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending distribution of a farm lot, without transport.
 * Real hand-overs go through LotDispatcher.
 *
 * @extends Factory<Distribution>
 */
class DistributionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lot_id' => fn () => Production::factory()->create()->lot->id,
            'sender_id' => fn (array $attributes) => Lot::find($attributes['lot_id'])->current_holder_id,
            'distributor_id' => User::factory()->distributor(),
            'destination' => 'Tunis',
            'latitude' => OrganizationFactory::CITIES['Tunis'][0],
            'longitude' => OrganizationFactory::CITIES['Tunis'][1],
            'quantity' => fn (array $attributes) => Lot::find($attributes['lot_id'])->quantity,
            'distribution_date' => now()->subDays(2)->toDateString(),
            'status' => DistributionStatus::PENDING,
        ];
    }
}
