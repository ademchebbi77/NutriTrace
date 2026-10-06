<?php

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Lot;
use App\Models\Transformation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A transformation with its output lot but no source lot, for tests of the module alone.
 * Real transformations go through TransformationService, which consumes the source lots.
 *
 * @extends Factory<Transformation>
 */
class TransformationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transformer_id' => User::factory()->transformer(),
            'output_lot_id' => fn (array $attributes) => Lot::factory()->create(['current_holder_id' => $attributes['transformer_id']])->id,
            'location_label' => 'Sfax',
            'latitude' => OrganizationFactory::CITIES['Sfax'][0],
            'longitude' => OrganizationFactory::CITIES['Sfax'][1],
            'input_quantity' => 1000,
            'input_unit' => Unit::KILOGRAM,
            'output_quantity' => fn (array $attributes) => Lot::find($attributes['output_lot_id'])->quantity,
            'transformation_date' => now()->subWeek()->toDateString(),
            'process_description' => fake()->sentence(10),
            'energy_used_kwh' => fake()->numberBetween(50, 500),
            'water_used_l' => fake()->numberBetween(200, 3000),
        ];
    }
}
