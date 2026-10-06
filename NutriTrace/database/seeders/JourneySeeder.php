<?php

namespace Database\Seeders;

use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use App\Services\LotDispatcher;
use App\Services\LotReceptionService;
use App\Services\SaleService;
use App\Services\TransformationService;
use Database\Seeders\Concerns\DrivesTheChain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Moves the farm lots through the chain with the real services, so every
 * traceability event, hash and score of the demo is produced by the application.
 */
class JourneySeeder extends Seeder
{
    use DrivesTheChain;

    /** @var array<string, User> */
    private array $users;

    public function __construct(
        private readonly LotDispatcher $dispatcher,
        private readonly LotReceptionService $receptions,
        private readonly TransformationService $transformations,
        private readonly SaleService $sales,
    ) {}

    public function run(): void
    {
        $this->users = User::with('organization')->get()->keyBy('email')->all();

        $mill = $this->users['transformateur@nutritrace.test'];
        $dairy = $this->users['transformateur2@nutritrace.test'];
        $tunis = $this->users['distributeur@nutritrace.test'];
        $sahel = $this->users['distributeur2@nutritrace.test'];

        // Reference journey: 5 000 kg of olives in Sfax -> 900 L of oil -> Tunis by truck (270 km) -> store.
        $olives = $this->farmLot('Olives Chemlali', '2025-11-20');
        $this->toTransformer($olives, $mill, '2025-11-21 08:00', '2025-11-21 09:15');
        $oil = $this->transform($mill, [[$olives, 5000]], 'Huile d\'olive extra vierge', 900, 'L', '2025-11-22', '2027-11-22',
            'Lavage, broyage et extraction à froid en continu, décantation puis filtration. Mise en bouteille sur place.', 450, 2500);
        $distribution = $this->toDistributor($oil, $mill, $tunis, 'truck', '2025-12-01 07:00', '2025-12-01 12:30', 270, '2025-12-02 09:00');
        $this->sell($distribution, [['2026-01-15 11:00', 250], ['2026-03-10 16:30', 180]]);

        // Second harvest: only part of the lot is pressed, the oil is still at the mill.
        $olives2 = $this->farmLot('Olives Chemlali', '2025-12-10');
        $this->toTransformer($olives2, $mill, '2025-12-11 08:30', '2025-12-11 09:45');
        $this->transform($mill, [[$olives2, 3000]], 'Huile d\'olive extra vierge', 540, 'L', '2025-12-12', '2027-12-12',
            'Extraction à froid de la seconde récolte.', 270, 1500);

        // Table olives sold in Tunis: far from the grove despite a "local" label.
        $tableOlives = $this->farmLot('Olives de table Meski', '2025-10-28');
        $this->toDistributor($tableOlives, $this->holder($tableOlives), $tunis, 'truck', '2025-11-05 06:30', '2025-11-05 11:00', 270, '2025-11-06 08:30');

        // Milk from Béja becomes cheese, sold in Tunis: a genuinely local product.
        $milk = $this->farmLot('Lait cru de vache', '2026-09-28');
        $this->toTransformer($milk, $dairy, '2026-09-28 15:00', '2026-09-28 16:00', 'van');
        $cheese = $this->transform($dairy, [[$milk, 1800]], 'Fromage Sicilien de Béja', 210, 'kg', '2026-09-29', '2026-10-20',
            'Pasteurisation, emprésurage, moulage et salage. Affinage court en cave.', 320, 900);
        $cheeseDistribution = $this->toDistributor($cheese, $dairy, $tunis, 'van', '2026-09-30 06:00', '2026-09-30 08:30', null, '2026-09-30 10:00');
        $this->sell($cheeseDistribution, [['2026-10-02 12:00', 60]]);

        // An earlier milk collection, turned into cheese that has not left the dairy yet.
        $milk0 = $this->farmLot('Lait cru de vache', '2026-09-20');
        $this->toTransformer($milk0, $dairy, '2026-09-20 15:00', '2026-09-20 16:10', 'van');
        $this->transform($dairy, [[$milk0, 1500]], 'Fromage Sicilien de Béja', 175, 'kg', '2026-09-21', '2026-10-12',
            'Pasteurisation, emprésurage, moulage et salage.', 270, 760);

        // The latest milk collection is on the road: the dairy has not confirmed it yet.
        $milk2 = $this->farmLot('Lait cru de vache', '2026-10-01');
        $this->dispatcher->send($milk2, $this->holder($milk2), $dairy, [
            'transport_type' => 'van',
            'departure_date' => '2026-10-04 15:00',
            'note' => 'Collecte du dimanche, cuve n° 2.',
        ]);

        // Tomatoes: one lot sold out in Tunis, one in store in Tunis, one in Sousse.
        $tomatoes = $this->farmLot('Tomates de plein champ', '2026-07-20');
        $soldOut = $this->toDistributor($tomatoes, $this->holder($tomatoes), $tunis, 'truck', '2026-07-21 05:30', '2026-07-21 07:30', null, '2026-07-21 09:00');
        $this->sell($soldOut, [['2026-07-24 18:00', 1800], ['2026-07-29 18:00', 1200]]);

        $tomatoes2 = $this->farmLot('Tomates de plein champ', '2026-08-12');
        $shelf = $this->toDistributor($tomatoes2, $this->holder($tomatoes2), $tunis, 'truck', '2026-08-13 05:30', '2026-08-13 07:30', null, '2026-08-13 09:00');
        $this->sell($shelf, [['2026-08-18 18:00', 2100]]);

        $tomatoes3 = $this->farmLot('Tomates de plein champ', '2026-09-02');
        $this->toDistributor($tomatoes3, $this->holder($tomatoes3), $sahel, 'truck', '2026-09-03 05:00', '2026-09-03 08:00', 160, '2026-09-03 10:00');

        // Oranges: one season sold out in Tunis, the next one in store in Sousse.
        $oranges = $this->farmLot('Oranges Maltaises', '2026-01-20');
        $orangesTunis = $this->toDistributor($oranges, $this->holder($oranges), $tunis, 'truck', '2026-01-21 06:00', '2026-01-21 08:00', null, '2026-01-21 10:00');
        $this->sell($orangesTunis, [['2026-02-10 17:00', 3.5]]);

        $oranges2 = $this->farmLot('Oranges Maltaises', '2026-02-15');
        $this->toDistributor($oranges2, $this->holder($oranges2), $sahel, 'truck', '2026-02-16 06:00', '2026-02-16 09:30', null, '2026-02-17 08:00');

        // Honey: last year's in Sousse, this year's in Tunis.
        $honey = $this->farmLot('Miel de thym', '2025-07-15');
        $honeySahel = $this->toDistributor($honey, $this->holder($honey), $sahel, 'van', '2025-07-20 07:00', '2025-07-20 09:30', null, '2025-07-21 09:00');
        $this->sell($honeySahel, [['2025-12-20 11:00', 180]]);

        $honey2 = $this->farmLot('Miel de thym', '2026-07-10');
        $honeyTunis = $this->toDistributor($honey2, $this->holder($honey2), $tunis, 'van', '2026-07-12 07:00', '2026-07-12 08:15', null, '2026-07-12 10:00');
        $this->sell($honeyTunis, [['2026-08-05 15:00', 90]]);

        // A refused delivery: the rosemary honey goes back to the beekeeper.
        $rosemary = $this->farmLot('Miel de romarin', '2026-05-22');
        $refused = $this->dispatcher->send($rosemary, $this->holder($rosemary), $sahel, [
            'transport_type' => 'van',
            'departure_date' => '2026-06-02 07:00',
        ]);
        $this->receptions->rejectDistribution($refused, 'Pots livrés sans étiquette de lot.', Carbon::parse('2026-06-02 10:00'));

        // Wheat and peppers stay on the farm for now.
    }
}
