<?php

namespace Database\Factories;

use App\Enums\LotStatus;
use App\Enums\Unit;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Standalone lot without a production (as produced by a transformation).
 * For a farm lot, create a Production: its lot is generated automatically.
 *
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'current_holder_id' => User::factory()->transformer(),
            'product_id' => fn (array $attributes) => Product::factory()->state(['created_by' => $attributes['current_holder_id']]),
            'production_id' => null,
            'quantity' => fake()->numberBetween(100, 2000),
            'unit' => Unit::KILOGRAM,
            'production_date' => fake()->dateTimeBetween('-3 months', '-1 week')->format('Y-m-d'),
            'expiration_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'status' => LotStatus::CREATED,
        ];
    }

    public function heldBy(User $user): static
    {
        return $this->state(fn () => ['current_holder_id' => $user->id]);
    }

    public function status(LotStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
