<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Precise location of each demo organization, so distances between actors are realistic.
     *
     * @var array<string, array{float, float}>
     */
    private const COORDINATES = [
        'producteur@nutritrace.test' => [34.6210, 10.5820],
        'producteur2@nutritrace.test' => [36.7650, 9.0830],
        'producteur3@nutritrace.test' => [36.5786, 10.8586],
        'producteur4@nutritrace.test' => [36.3890, 10.1120],
        'transformateur@nutritrace.test' => [34.7790, 10.7980],
        'transformateur2@nutritrace.test' => [36.7256, 9.1817],
        'distributeur@nutritrace.test' => [36.8008, 10.1800],
        'distributeur2@nutritrace.test' => [35.8370, 10.5900],
    ];

    /**
     * Demo accounts. Every password is "password".
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Amel Ben Salah',
            'email' => 'admin@nutritrace.test',
            'phone' => '+216 71 000 000',
        ]);

        User::factory()->create([
            'name' => 'Youssef Trabelsi',
            'email' => 'consommateur@nutritrace.test',
            'phone' => '+216 22 345 678',
        ]);

        User::factory()->create([
            'name' => 'Ines Gharbi',
            'email' => 'consommateur2@nutritrace.test',
        ]);

        $professionals = [
            // [email, name, role, organization, city, address, registration number, description, verified]
            ['producteur@nutritrace.test', 'Mohamed Chaâbane', UserRole::PRODUCTEUR, 'Domaine Chaâl', 'Sfax', 'Route de Gabès, km 22', '1234567A', 'Oliveraie familiale de 120 hectares conduite en agriculture biologique.', true],
            ['producteur2@nutritrace.test', 'Salma Ben Youssef', UserRole::PRODUCTEUR, 'Ferme El Baraka', 'Béja', 'Route de Nefza, Amdoun', '2345678B', 'Élevage laitier et cultures céréalières dans le nord-ouest.', true],
            ['producteur3@nutritrace.test', 'Hedi Mansouri', UserRole::PRODUCTEUR, 'Maraîchers du Cap Bon', 'Nabeul', 'Zone agricole de Korba', '3456789C', 'Coopérative de maraîchers : tomates, piments et agrumes.', false],
            ['producteur4@nutritrace.test', 'Leila Jaziri', UserRole::PRODUCTEUR, 'Rucher du Zaghouan', 'Zaghouan', 'Djebel Zaghouan', '4567890D', 'Miel de thym et de romarin récolté en montagne.', true],
            ['transformateur@nutritrace.test', 'Karim Mezghani', UserRole::TRANSFORMATEUR, 'Huilerie Sidi Mansour', 'Sfax', 'Zone industrielle Poudrière 2', '5678901E', 'Huilerie à extraction à froid, trituration dans les 24 heures.', true],
            ['transformateur2@nutritrace.test', 'Nadia Oueslati', UserRole::TRANSFORMATEUR, 'Fromagerie de Béja', 'Béja', 'Avenue de l\'Environnement', '6789012F', 'Fromages et produits laitiers à partir de lait collecté localement.', false],
            ['distributeur@nutritrace.test', 'Sami Belhadj', UserRole::DISTRIBUTEUR, 'Marché Vert Tunis', 'Tunis', '14 rue de Marseille', '7890123G', 'Épiceries de produits locaux et biologiques à Tunis.', true],
            ['distributeur2@nutritrace.test', 'Rim Hamdi', UserRole::DISTRIBUTEUR, 'Épicerie du Sahel', 'Sousse', 'Avenue Yasser Arafat, Sahloul', '8901234H', 'Distribution de produits du terroir dans le Sahel.', false],
        ];

        foreach ($professionals as [$email, $name, $role, $organization, $city, $address, $registration, $description, $verified]) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subMonths(2),
            ]);

            Organization::factory()->inCity($city)->create([
                'user_id' => $user->id,
                'name' => $organization,
                'latitude' => self::COORDINATES[$email][0],
                'longitude' => self::COORDINATES[$email][1],
                'address' => $address,
                'registration_number' => $registration,
                'description' => $description,
                'is_verified' => $verified,
                'verified_by' => $verified ? $admin->id : null,
                'verified_at' => $verified ? now()->subMonths(2) : null,
            ]);
        }

        // Accounts that illustrate the approval workflow.
        $pending = User::factory()->pending()->create([
            'name' => 'Anis Bouazizi',
            'email' => 'en-attente@nutritrace.test',
            'role' => UserRole::TRANSFORMATEUR,
        ]);

        Organization::factory()->inCity('Sousse')->create([
            'user_id' => $pending->id,
            'name' => 'Conserverie du Sahel',
            'address' => 'Zone industrielle de Sidi Abdelhamid',
            'description' => 'Conserves de tomates et harissa.',
        ]);

        $rejected = User::factory()->create([
            'name' => 'Walid Dridi',
            'email' => 'refuse@nutritrace.test',
            'role' => UserRole::DISTRIBUTEUR,
            'account_status' => AccountStatus::REJECTED,
            'is_active' => false,
            'rejection_reason' => 'Matricule fiscal introuvable au registre national des entreprises.',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now()->subWeek(),
        ]);

        Organization::factory()->inCity('Kairouan')->create([
            'user_id' => $rejected->id,
            'name' => 'Négoce Kairouan',
            'registration_number' => null,
        ]);
    }
}
