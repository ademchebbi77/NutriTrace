<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Tunisian cities with their coordinates, used for realistic demo data.
     *
     * @var array<string, array{float, float}>
     */
    public const CITIES = [
        'Tunis' => [36.8065, 10.1815],
        'Sfax' => [34.7406, 10.7603],
        'Sousse' => [35.8256, 10.6411],
        'Bizerte' => [37.2744, 9.8739],
        'Béja' => [36.7256, 9.1817],
        'Nabeul' => [36.4561, 10.7376],
        'Kairouan' => [35.6781, 10.0963],
        'Zaghouan' => [36.4029, 10.1429],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->randomElement(array_keys(self::CITIES));

        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'registration_number' => fake()->numerify('#######').fake()->randomLetter(),
            'address' => fake()->streetAddress(),
            'city' => $city,
            'latitude' => self::CITIES[$city][0],
            'longitude' => self::CITIES[$city][1],
            'description' => fake()->sentence(12),
            'is_verified' => false,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    public function inCity(string $city): static
    {
        return $this->state(fn () => [
            'city' => $city,
            'latitude' => self::CITIES[$city][0],
            'longitude' => self::CITIES[$city][1],
        ]);
    }
}
