<?php

namespace Database\Factories;

use App\Enums\TransportStatus;
use App\Enums\TransportType;
use App\Enums\Unit;
use App\Models\Lot;
use App\Models\Production;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A stand-alone transport of a farm lot, for tests of the Transport module alone.
 * Real hand-overs go through LotDispatcher, which also creates the transfer or distribution.
 *
 * @extends Factory<Transport>
 */
class TransportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lot_id' => fn () => Production::factory()->create()->lot->id,
            'shipper_id' => fn (array $attributes) => Lot::find($attributes['lot_id'])->current_holder_id,
            'recipient_id' => User::factory()->distributor(),
            'origin_label' => 'Sfax',
            'origin_latitude' => OrganizationFactory::CITIES['Sfax'][0],
            'origin_longitude' => OrganizationFactory::CITIES['Sfax'][1],
            'destination_label' => 'Tunis',
            'destination_latitude' => OrganizationFactory::CITIES['Tunis'][0],
            'destination_longitude' => OrganizationFactory::CITIES['Tunis'][1],
            'transport_type' => TransportType::TRUCK,
            'distance_km' => 270,
            'departure_date' => now()->subDays(2),
            'quantity_transported' => 1000,
            'unit' => Unit::KILOGRAM,
            'status' => TransportStatus::IN_TRANSIT,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => TransportStatus::DELIVERED, 'arrival_date' => now()->subDay()]);
    }
}
