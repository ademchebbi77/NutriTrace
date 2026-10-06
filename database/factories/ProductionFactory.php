<?php

namespace Database\Factories;

use App\Enums\ProductionMethod;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creating a production also creates its initial lot (see ProductionObserver).
 *
 * @extends Factory<Production>
 */
class ProductionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement(array_keys(OrganizationFactory::CITIES));

        return [
            'producer_id' => User::factory()->producer(),
            // The product belongs to the same producer.
            'product_id' => fn (array $attributes) => Product::factory()->state(['created_by' => $attributes['producer_id']]),
            'location_address' => fake()->streetAddress(),
            'location_city' => $city,
            'latitude' => OrganizationFactory::CITIES[$city][0],
            'longitude' => OrganizationFactory::CITIES[$city][1],
            'production_date' => fake()->dateTimeBetween('-6 months', '-1 week')->format('Y-m-d'),
            'quantity' => fake()->numberBetween(100, 5000),
            'unit' => Unit::KILOGRAM,
            'production_method' => fake()->randomElement(ProductionMethod::cases()),
            'resources_used' => [
                'water_l' => fake()->numberBetween(1000, 50000),
                'energy_kwh' => fake()->numberBetween(50, 800),
            ],
        ];
    }

    /**
     * Production by the given producer, of one of their products.
     */
    public function by(User $producer, ?Product $product = null): static
    {
        return $this->state(fn () => [
            'producer_id' => $producer->id,
            'product_id' => $product?->id ?? Product::factory()->state(['created_by' => $producer->id]),
        ]);
    }
}
