<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Certification;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Database\Seeder;

/**
 * Consumer activity: reviews, reports in every status, history and favorites.
 */
class CommunitySeeder extends Seeder
{
    public function run(ReportService $reports): void
    {
        $admin = User::where('email', 'admin@nutritrace.test')->firstOrFail();
        $youssef = User::where('email', 'consommateur@nutritrace.test')->firstOrFail();
        $ines = User::where('email', 'consommateur2@nutritrace.test')->firstOrFail();

        $oil = $this->lot('Huile d\'olive extra vierge');
        $cheese = $this->lot('Fromage Sicilien de Béja');
        $honey = $this->lot('Miel de thym');
        $tomatoes = $this->lot('Tomates de plein champ');
        $tableOlives = $this->lot('Olives de table Meski');

        $reviews = [
            [$youssef, $oil, 5, 'Excellente huile, fruitée. On voit tout le parcours depuis l\'oliveraie de Sfax.'],
            [$ines, $oil, 4, 'Très bonne, et la fiche est claire sur l\'empreinte du transport.'],
            [$youssef, $honey, 5, 'Miel parfumé. Le certificat bio est consultable, c\'est rassurant.'],
            [$ines, $cheese, 5, 'Fromage frais et vraiment local : moins de 100 km entre la ferme et le magasin.'],
            [$youssef, $tomatoes, 3, 'Bonnes tomates, mais la mention bio vue en rayon n\'est pas prouvée ici.'],
            [$ines, $tableOlives, 2, 'Présentées comme locales à Tunis alors qu\'elles viennent de Sfax.'],
        ];

        foreach ($reviews as [$user, $lot, $rating, $comment]) {
            $review = new Review(['rating' => $rating, 'comment' => $comment]);
            $review->user_id = $user->id;
            $review->product_id = $lot->product_id;
            $review->lot_id = $lot->id;
            $review->save();

            $user->viewedLots()->syncWithoutDetaching([$lot->id => ['viewed_at' => now()->subDays(random_int(1, 20))]]);
        }

        $youssef->favoriteProducts()->syncWithoutDetaching([
            $oil->product_id => ['created_at' => now()],
            $honey->product_id => ['created_at' => now()],
        ]);
        $ines->favoriteProducts()->syncWithoutDetaching([$cheese->product_id => ['created_at' => now()]]);

        // Pending: nobody has looked at it yet.
        $reports->submit($youssef, $tomatoes->product, [
            'type' => ReportType::MISLEADING_ENVIRONMENTAL_CLAIM->value,
            'description' => 'Ces tomates sont vendues avec une affichette « bio » en magasin alors qu\'aucun certificat valide n\'apparaît sur la fiche.',
        ]);

        // Under review: shows the banner on the public page of the lot.
        $underReview = $reports->submit($ines, $tableOlives, [
            'type' => ReportType::SUSPICIOUS_INFORMATION->value,
            'description' => 'Étiquetées « produit local » à Tunis, mais le parcours indique une récolte à Sfax et 270 km de camion.',
        ]);
        $reports->moderate($underReview, $admin, ReportStatus::UNDER_REVIEW, null);

        $resolved = $reports->submit($youssef, $cheese, [
            'type' => ReportType::SUSPICIOUS_INFORMATION->value,
            'description' => 'La date limite de consommation affichée en rayon ne correspondait pas à celle de la fiche du lot.',
        ]);
        $reports->moderate($resolved, $admin, ReportStatus::RESOLVED, 'Vérifié avec le distributeur : l\'étiquette en rayon a été corrigée, la date de la fiche est la bonne.');

        $expired = Certification::where('certificate_number', 'INN-2023-554')->firstOrFail();
        $rejected = $reports->submit($ines, $expired, [
            'type' => ReportType::INVALID_CERTIFICATION->value,
            'description' => 'Ce certificat d\'agriculture durable me semble périmé depuis janvier.',
        ]);
        $reports->moderate($rejected, $admin, ReportStatus::REJECTED, 'Le certificat est déjà affiché comme expiré et n\'est plus pris en compte : aucune action supplémentaire n\'est nécessaire.');
    }

    /**
     * Most recent public lot of a product that has reached a store.
     */
    private function lot(string $product): Lot
    {
        return Lot::query()
            ->whereHas('product', fn ($query) => $query->where('name', $product))
            ->whereHas('distributions')
            ->orderBy('production_date')
            ->firstOrFail();
    }
}
