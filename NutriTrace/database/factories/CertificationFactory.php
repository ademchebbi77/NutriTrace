<?php

namespace Database\Factories;

use App\Enums\CertificationStatus;
use App\Enums\CertificationType;
use App\Models\Certification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certification>
 */
class CertificationFactory extends Factory
{
    /**
     * A pending certification with its proof, attached to a product by default.
     * Use ->for($lot, 'certifiable') to attach it to a lot.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->producer(),
            'certifiable_type' => (new Product)->getMorphClass(),
            'certifiable_id' => Product::factory(),
            'name' => 'Agriculture durable',
            'type' => CertificationType::SUSTAINABLE_AGRICULTURE,
            'issuing_organization' => 'INNORPI',
            'certificate_number' => 'TN-'.fake()->unique()->numerify('######'),
            'issue_date' => now()->subMonths(6)->toDateString(),
            'expiration_date' => now()->addMonths(18)->toDateString(),
            'document_path' => 'certifications/demo.pdf',
            'status' => CertificationStatus::PENDING,
        ];
    }

    public function bio(): static
    {
        return $this->state(fn () => ['name' => 'Agriculture biologique', 'type' => CertificationType::BIO, 'issuing_organization' => 'Ecocert']);
    }

    public function local(): static
    {
        return $this->state(fn () => ['name' => 'Produit local', 'type' => CertificationType::LOCAL]);
    }

    public function verified(): static
    {
        return $this->state(fn () => ['status' => CertificationStatus::VERIFIED, 'reviewed_at' => now()]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => CertificationStatus::REJECTED,
            'rejection_reason' => 'Numéro de certificat introuvable auprès de l\'organisme.',
            'reviewed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => CertificationStatus::EXPIRED,
            'issue_date' => now()->subYears(3)->toDateString(),
            'expiration_date' => now()->subMonths(2)->toDateString(),
        ]);
    }

    public function withoutProof(): static
    {
        return $this->state(fn () => ['document_path' => null, 'certificate_number' => null]);
    }
}
