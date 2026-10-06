<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'created_by' => User::factory()->producer(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(15),
            'origin' => fake()->randomElement(array_keys(OrganizationFactory::CITIES)).', Tunisie',
            'status' => ProductStatus::PUBLISHED,
            'barcode' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::DRAFT]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::ARCHIVED]);
    }
}
