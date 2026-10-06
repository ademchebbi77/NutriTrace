<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * A pending report about a product. Use ->for($lot, 'reportable') for another target.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reportable_type' => (new Product)->getMorphClass(),
            'reportable_id' => Product::factory(),
            'type' => ReportType::SUSPICIOUS_INFORMATION,
            'description' => fake()->sentence(14),
            'status' => ReportStatus::PENDING,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn () => ['status' => ReportStatus::UNDER_REVIEW]);
    }
}
