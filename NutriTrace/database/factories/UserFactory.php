<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: an active, verified consumer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->optional()->numerify('+216 ## ### ###'),
            'role' => UserRole::CONSOMMATEUR,
            'account_status' => AccountStatus::APPROVED,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN]);
    }

    /**
     * Approved professional account with its organization.
     */
    public function professional(UserRole $role): static
    {
        return $this->state(fn () => ['role' => $role])->has(Organization::factory());
    }

    public function producer(): static
    {
        return $this->professional(UserRole::PRODUCTEUR);
    }

    public function transformer(): static
    {
        return $this->professional(UserRole::TRANSFORMATEUR);
    }

    public function distributor(): static
    {
        return $this->professional(UserRole::DISTRIBUTEUR);
    }

    /**
     * Professional account still waiting for an admin approval.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'account_status' => AccountStatus::PENDING,
            'is_active' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
