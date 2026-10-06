<?php

namespace Database\Seeders;

use App\Enums\ProductionMethod;
use App\Enums\ProductStatus;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Enums\Unit;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Certification;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Rules\Ean;
use App\Services\CertificationService;
use App\Services\LotDispatcher;
use App\Services\LotReceptionService;
use App\Services\ProductionService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\Scoring\FootprintCalculator;
use App\Services\Scoring\LotScoreManager;
use App\Services\TransformationService;
use Database\Seeders\Concerns\DrivesTheChain;
use Database\Seeders\Concerns\MakesProductImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A larger, realistic data set on top of the base demo: more Tunisian regions, actors,
 * products and lots with complete journeys, certifications, reviews and reports.
 *
 * Organizations and people are invented; regions, crop varieties, certification bodies
 * and orders of magnitude are real. It only adds records and can be run on its own:
 *
 *     php artisan db:seed --class=VolumeSeeder
 */
class VolumeSeeder extends Seeder
{
    use DrivesTheChain, MakesProductImages;

    private const DEMO_DOCUMENT = 'certifications/demo-certificat.pdf';

    /** @var array<string, User> */
    private array $actors = [];

    private User $admin;

    public function __construct(
        private readonly LotDispatcher $dispatcher,
        private readonly LotReceptionService $receptions,
        private readonly TransformationService $transformations,
        private readonly SaleService $sales,
        private readonly ProductionService $productions,
        private readonly CertificationService $certifications,
        private readonly ReportService $reports,
        private readonly FootprintCalculator $footprint,
        private readonly LotScoreManager $scores,
    ) {}

    public function run(): void
    {
        if (User::where('email', 'producteur5@nutritrace.test')->exists()) {
            $this->command?->warn('VolumeSeeder: data already present, nothing added.');

            return;
        }

        // Same "random" choices on every run.
        mt_srand(2026);

        $this->admin = User::where('role', UserRole::ADMIN)->firstOrFail();

        // All or nothing: a failure half-way leaves the existing data exactly as it was.
        DB::transaction(function () {
            $this->createActors();
            $this->createProducts();
            $this->createProductions();
            $this->driveJourneys();
            $this->createCertifications();
            $this->declareImpacts();
            $this->createCommunity();
        });
    }

    private function createActors(): void
    {
        $professionals = [
            // [key, email, name, role, organization, city, latitude, longitude, address, registration, description, verified]
            ['dates', 'producteur5@nutritrace.test', 'Abdelkader Ben Amor', UserRole::PRODUCTEUR, 'Coopérative des Oasis de Nefzaoua', 'Kébili', 33.7044, 8.9690, 'Oasis de Douz, route de Kébili', '9012345J', 'Coopérative de phœniciculteurs : dattes Deglet Nour cultivées sous palmeraie à trois étages.', true],
            ['kairouan', 'producteur6@nutritrace.test', 'Fatma Zouari', UserRole::PRODUCTEUR, 'Vergers de Kairouan', 'Kairouan', 35.6781, 10.0963, 'Route de Haffouz, km 9', '9123456K', 'Vergers d\'abricotiers et d\'amandiers en culture pluviale.', true],
            ['kasserine', 'producteur7@nutritrace.test', 'Mounir Gharsalli', UserRole::PRODUCTEUR, 'Coopérative de Zelfène', 'Kasserine', 35.1676, 8.8365, 'Zelfène, délégation de Thala', '9234567L', 'Figues de Barbarie et pistaches des hauts plateaux.', false],
            ['testour', 'producteur8@nutritrace.test', 'Hichem Andoulsi', UserRole::PRODUCTEUR, 'Les Jardins de Testour', 'Testour', 36.5517, 9.4433, 'Vallée de la Medjerda', '9345678M', 'Vergers de grenadiers de variété Gabsi le long de la Medjerda.', true],
            ['mornag', 'producteur9@nutritrace.test', 'Sonia Ben Romdhane', UserRole::PRODUCTEUR, 'Domaine de Mornag', 'Mornag', 36.6833, 10.2833, 'Route de Khelidia', '9456789N', 'Vignoble de raisins de table Muscat d\'Italie.', false],
            ['oasis', 'transformateur3@nutritrace.test', 'Tarek Souissi', UserRole::TRANSFORMATEUR, 'Conditionnement des Oasis', 'Tozeur', 33.9197, 8.1335, 'Zone industrielle de Tozeur', '9567890P', 'Tri, calibrage et conditionnement de dattes en coffrets.', true],
            ['conserverie', 'transformateur4@nutritrace.test', 'Imen Kacem', UserRole::TRANSFORMATEUR, 'Conserverie du Cap Bon', 'Nabeul', 36.4561, 10.7376, 'Zone industrielle de Dar Chaâbane', '9678901Q', 'Harissa traditionnelle et concentré de tomates.', true],
            ['moulin', 'transformateur5@nutritrace.test', 'Lotfi Mejri', UserRole::TRANSFORMATEUR, 'Moulin de Mateur', 'Mateur', 37.0400, 9.6650, 'Avenue de la République', '9789012R', 'Minoterie de blé dur : semoule et couscous.', false],
            ['sfax', 'distributeur3@nutritrace.test', 'Nizar Feki', UserRole::DISTRIBUTEUR, 'Souk Bio Sfax', 'Sfax', 34.7406, 10.7603, 'Avenue Majida Boulila', '9890123S', 'Magasin de produits biologiques et du terroir.', true],
            ['bizerte', 'distributeur4@nutritrace.test', 'Asma Jebali', UserRole::DISTRIBUTEUR, 'Les Halles de Bizerte', 'Bizerte', 37.2744, 9.8739, 'Quai du Vieux Port', '9901234T', 'Halle de producteurs du nord.', true],
            ['marsa', 'distributeur5@nutritrace.test', 'Mehdi Ayari', UserRole::DISTRIBUTEUR, 'Épicerie fine de La Marsa', 'La Marsa', 36.8782, 10.3247, 'Rue du Maroc', '9012346U', 'Épicerie fine : fruits secs, miels et conserves artisanales.', false],
        ];

        foreach ($professionals as [$key, $email, $name, $role, $organization, $city, $latitude, $longitude, $address, $registration, $description, $verified]) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'reviewed_by' => $this->admin->id,
                'reviewed_at' => now()->subMonths(14),
            ]);

            Organization::factory()->create([
                'user_id' => $user->id,
                'name' => $organization,
                'city' => $city,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $address,
                'registration_number' => $registration,
                'description' => $description,
                'is_verified' => $verified,
                'verified_by' => $verified ? $this->admin->id : null,
                'verified_at' => $verified ? now()->subMonths(14) : null,
            ]);

            $this->actors[$key] = $user->load('organization');
        }

        // Actors of the base demo, reused in the new journeys.
        foreach ([
            'baraka' => 'producteur2@nutritrace.test',
            'capbon' => 'producteur3@nutritrace.test',
            'rucher' => 'producteur4@nutritrace.test',
            'dairy' => 'transformateur2@nutritrace.test',
            'tunis' => 'distributeur@nutritrace.test',
            'sahel' => 'distributeur2@nutritrace.test',
        ] as $key => $email) {
            $this->actors[$key] = User::with('organization')->where('email', $email)->firstOrFail();
        }

        foreach ([
            ['consommateur3@nutritrace.test', 'Rania Chebbi'],
            ['consommateur4@nutritrace.test', 'Omar Sassi'],
            ['consommateur5@nutritrace.test', 'Nour El Houda Tlili'],
            ['consommateur6@nutritrace.test', 'Khalil Mabrouk'],
            ['consommateur7@nutritrace.test', 'Syrine Belaid'],
            ['consommateur8@nutritrace.test', 'Aymen Karoui'],
        ] as [$email, $name]) {
            User::factory()->create(['name' => $name, 'email' => $email]);
        }
    }

    private function createProducts(): void
    {
        $driedFruits = Category::firstOrCreate(['name' => 'Fruits secs et dattes']);
        $fresh = Category::where('name', 'Fruits et légumes')->firstOrFail();
        $grocery = Category::where('name', 'Conserves et épicerie')->firstOrFail();
        $cereals = Category::where('name', 'Céréales')->firstOrFail();

        $products = [
            // [owner, name, category, origin, colour, description]
            ['dates', 'Dattes Deglet Nour', $driedFruits, 'Kébili, Tunisie', '#8a5a2b', 'Dattes Deglet Nour en branchettes, récoltées à la main dans les oasis de Nefzaoua.'],
            ['kairouan', 'Abricots de Kairouan', $fresh, 'Kairouan, Tunisie', '#f4a259', 'Abricots précoces cueillis à maturité.'],
            ['kairouan', 'Amandes de Kairouan', $driedFruits, 'Kairouan, Tunisie', '#c8a27a', 'Amandes douces séchées au soleil, vendues en coque ou décortiquées.'],
            ['kasserine', 'Figues de Barbarie', $fresh, 'Kasserine, Tunisie', '#c0504d', 'Figues de Barbarie des hauts plateaux, cultivées sans irrigation.'],
            ['kasserine', 'Pistaches de Kasserine', $driedFruits, 'Kasserine, Tunisie', '#9bbb59', 'Pistaches de variété Mateur, séchées naturellement.'],
            ['testour', 'Grenades de Testour', $fresh, 'Testour, Béja, Tunisie', '#a61c3c', 'Grenades Gabsi à gros grains, spécialité de Testour.'],
            ['mornag', 'Raisins de table Muscat', $fresh, 'Mornag, Ben Arous, Tunisie', '#7b5ea7', 'Raisins Muscat d\'Italie à grains dorés.'],
            ['oasis', 'Dattes Deglet Nour en coffret', $driedFruits, 'Tozeur, Tunisie', '#6b4423', 'Dattes triées, calibrées et conditionnées en coffrets d\'un kilo.'],
            ['conserverie', 'Harissa traditionnelle', $grocery, 'Nabeul, Tunisie', '#b22222', 'Harissa de piments Baklouti séchés au soleil, ail, carvi et huile d\'olive.'],
            ['conserverie', 'Concentré de tomates', $grocery, 'Nabeul, Tunisie', '#d9453b', 'Double concentré de tomates de plein champ du Cap Bon.'],
            ['moulin', 'Semoule de blé dur', $cereals, 'Mateur, Bizerte, Tunisie', '#d4a017', 'Semoule moyenne de blé dur Karim, moulue sur cylindres.'],
            ['moulin', 'Couscous moyen', $cereals, 'Mateur, Bizerte, Tunisie', '#e0b354', 'Couscous roulé à partir de semoule de blé dur.'],
        ];

        foreach ($products as $index => [$owner, $name, $category, $origin, $color, $description]) {
            $payload = '619'.str_pad((string) (200000011 + $index * 173), 9, '0', STR_PAD_LEFT);

            $product = new Product([
                'category_id' => $category->id,
                'name' => $name,
                'description' => $description,
                'origin' => $origin,
                'status' => ProductStatus::PUBLISHED,
                'barcode' => $payload.Ean::checkDigit($payload),
            ]);

            $product->created_by = $this->actors[$owner]->id;
            $product->image_path = $this->placeholderImage($name, $color);
            $product->save();
        }
    }

    private function createProductions(): void
    {
        // Resources per unit produced: [water L, energy kWh, fertilizer kg, pesticide kg]. Null = not declared.
        $profiles = [
            'Dattes Deglet Nour' => [110000, 50, 10, 1],
            'Abricots de Kairouan' => [55, 0.06, 0.02, 0.002],
            'Amandes de Kairouan' => [140, 0.10, 0, 0],
            'Figues de Barbarie' => [5, 0.01, 0, 0],
            'Pistaches de Kasserine' => [120, 0.12, 0.02, 0.002],
            'Grenades de Testour' => [45000, 50, 15, 1],
            'Raisins de table Muscat' => [40, 0.07, 0.03, 0.004],
            'Tomates de plein champ' => [60, 0.09, 0.04, 0.0025],
            'Piments Baklouti' => [70, 0.10, 0.04, 0.003],
            'Blé dur Karim' => [0, 80, 40, 1],
            'Miel de romarin' => [0, 0.08, 0, 0],
            'Oranges Maltaises' => [60000, 75, 22, 1],
            'Lait cru de vache' => [5, 0.115, null, null],
        ];

        $rows = [
            // [product, date, quantity, unit, method, shelf life in days]
            ['Dattes Deglet Nour', '2025-10-25', 8, Unit::TONNE, ProductionMethod::ORGANIC, 365],
            ['Dattes Deglet Nour', '2025-11-10', 6, Unit::TONNE, ProductionMethod::ORGANIC, 365],
            ['Dattes Deglet Nour', '2025-11-28', 5, Unit::TONNE, ProductionMethod::ORGANIC, 365],
            ['Abricots de Kairouan', '2026-05-18', 2500, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 12],
            ['Abricots de Kairouan', '2026-06-02', 3100, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 12],
            ['Amandes de Kairouan', '2025-08-20', 900, Unit::KILOGRAM, ProductionMethod::ORGANIC, 540],
            ['Amandes de Kairouan', '2026-08-18', 1100, Unit::KILOGRAM, ProductionMethod::ORGANIC, 540],
            ['Figues de Barbarie', '2026-07-25', 1800, Unit::KILOGRAM, ProductionMethod::ORGANIC, 15],
            ['Figues de Barbarie', '2026-08-10', 2200, Unit::KILOGRAM, ProductionMethod::ORGANIC, 15],
            ['Pistaches de Kasserine', '2025-09-12', 600, Unit::KILOGRAM, ProductionMethod::INTEGRATED, 540],
            ['Pistaches de Kasserine', '2026-09-10', 750, Unit::KILOGRAM, ProductionMethod::INTEGRATED, 540],
            ['Grenades de Testour', '2025-10-15', 4, Unit::TONNE, ProductionMethod::INTEGRATED, 60],
            ['Grenades de Testour', '2025-11-02', 3.2, Unit::TONNE, ProductionMethod::INTEGRATED, 60],
            ['Raisins de table Muscat', '2026-07-30', 2600, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 20],
            ['Raisins de table Muscat', '2026-08-20', 3000, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 20],
            ['Raisins de table Muscat', '2026-09-05', 1900, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 20],
            ['Tomates de plein champ', '2026-06-28', 4200, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 14],
            ['Tomates de plein champ', '2026-08-25', 3800, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 14],
            ['Piments Baklouti', '2026-08-05', 1100, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 30],
            ['Piments Baklouti', '2026-09-01', 950, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, 30],
            ['Blé dur Karim', '2026-07-02', 9, Unit::TONNE, ProductionMethod::INTEGRATED, 365],
            ['Miel de romarin', '2026-04-15', 260, Unit::KILOGRAM, ProductionMethod::ORGANIC, 730],
            ['Oranges Maltaises', '2025-12-20', 3, Unit::TONNE, ProductionMethod::INTEGRATED, 45],
            ['Lait cru de vache', '2026-09-10', 1600, Unit::LITRE, ProductionMethod::CONVENTIONAL, 4],
            ['Lait cru de vache', '2026-09-24', 1700, Unit::LITRE, ProductionMethod::CONVENTIONAL, 4],
        ];

        $products = Product::with('creator.organization')->whereIn('name', array_keys($profiles))->get()->keyBy('name');

        foreach ($rows as [$name, $date, $quantity, $unit, $method, $shelfLife]) {
            $product = $products[$name];
            $organization = $product->creator->organization;

            // Each harvest differs a little from the profile of its crop.
            $resources = array_map(
                fn ($perUnit) => $perUnit === null ? null : round($perUnit * $quantity * (mt_rand(88, 112) / 100), 1),
                array_combine(['water_l', 'energy_kwh', 'fertilizer_kg', 'pesticide_kg'], $profiles[$name]),
            );

            $this->productions->create($product->creator, [
                'product_id' => $product->id,
                'location_address' => $organization->address,
                'location_city' => $organization->city,
                'latitude' => $organization->latitude,
                'longitude' => $organization->longitude,
                'production_date' => $date,
                'expiration_date' => Carbon::parse($date)->addDays($shelfLife)->toDateString(),
                'quantity' => $quantity,
                'unit' => $unit,
                'production_method' => $method,
                'resources_used' => $resources,
            ]);
        }
    }

    private function driveJourneys(): void
    {
        $a = $this->actors;

        // Dates: Kébili -> conditioning in Tozeur -> shops in the north.
        foreach ([['2025-10-25', 8, 'tunis', [0.4, 0.35]], ['2025-11-10', 6, 'marsa', [0.5]], ['2025-11-28', 5, null, []]] as [$date, $tonnes, $shop, $shares]) {
            $lot = $this->farmLot('Dattes Deglet Nour', $date);
            $this->toTransformer($lot, $a['oasis'], $this->at($date, 1, '07:00'), $this->at($date, 1, '09:30'));

            $coffrets = $this->transform($a['oasis'], [[$lot, $tonnes]], 'Dattes Deglet Nour en coffret', $tonnes * 900, 'kg',
                $this->day($date, 3), $this->day($date, 368),
                'Tri manuel, calibrage, hydratation à la vapeur puis conditionnement en coffrets d\'un kilo.', $tonnes * 45, $tonnes * 300);

            if ($shop) {
                $this->deliver($coffrets, $a['oasis'], $a[$shop], $this->day($date, 6), 'truck', $shares);
            }
        }

        // Durum wheat from Béja milled in Mateur.
        $wheat2025 = $this->farmLot('Blé dur Karim', '2025-06-28');
        $wheat2026 = $this->farmLot('Blé dur Karim', '2026-06-25');

        if ($wheat2025->isAvailable() && $wheat2026->isAvailable()) {
            $this->toTransformer($wheat2025, $a['moulin'], '2025-07-05 06:00', '2025-07-05 08:30');
            $semolina = $this->transform($a['moulin'], [[$wheat2025, 10]], 'Semoule de blé dur', 7200, 'kg', '2025-07-08', '2026-07-08',
                'Nettoyage, mouillage, mouture sur cylindres et sassage.', 620, 1200);
            $this->deliver($semolina, $a['moulin'], $a['bizerte'], '2025-07-15', 'truck', [0.5, 0.5]);

            $this->toTransformer($wheat2026, $a['moulin'], '2026-07-01 06:00', '2026-07-01 08:30');
            $couscous = $this->transform($a['moulin'], [[$wheat2026, 8]], 'Couscous moyen', 5600, 'kg', '2026-07-04', '2027-07-04',
                'Mouture en semoule, roulage, cuisson vapeur, séchage et calibrage.', 760, 2400);
            $this->deliver($couscous, $a['moulin'], $a['tunis'], '2026-07-10', 'truck', [0.3]);

            // The rest of the same wheat becomes semolina that has not left the mill yet.
            $this->transform($a['moulin'], [[$wheat2026->fresh(), 4]], 'Semoule de blé dur', 2900, 'kg', '2026-07-06', '2027-07-06',
                'Nettoyage, mouillage, mouture sur cylindres et sassage.', 250, 480);
        }

        // Cap Bon cannery: two pepper harvests in one harissa, tomatoes in concentrate.
        $peppers1 = $this->farmLot('Piments Baklouti', '2026-08-05');
        $peppers2 = $this->farmLot('Piments Baklouti', '2026-09-01');
        $this->toTransformer($peppers1, $a['conserverie'], '2026-08-06 07:00', '2026-08-06 08:00', 'van');
        $this->toTransformer($peppers2, $a['conserverie'], '2026-09-02 07:00', '2026-09-02 08:00', 'van');
        $harissa = $this->transform($a['conserverie'], [[$peppers1, 1100], [$peppers2, 500]], 'Harissa traditionnelle', 1150, 'kg', '2026-09-04', '2028-09-04',
            'Séchage au soleil, réhydratation, broyage avec ail, carvi, coriandre et sel, ajout d\'huile d\'olive et mise en pot.', 210, 900);
        $this->deliver($harissa, $a['conserverie'], $a['sfax'], '2026-09-10', 'truck', [0.25]);

        foreach ([['2026-06-28', 4200, 700, 'tunis', [0.6, 0.4]], ['2026-08-25', 3800, 640, 'marsa', [0.2]]] as [$date, $kg, $output, $shop, $shares]) {
            $tomatoes = $this->farmLot('Tomates de plein champ', $date);
            $this->toTransformer($tomatoes, $a['conserverie'], $this->at($date, 1, '06:00'), $this->at($date, 1, '07:00'));
            $concentrate = $this->transform($a['conserverie'], [[$tomatoes, $kg]], 'Concentré de tomates', $output, 'kg',
                $this->day($date, 3), $this->day($date, 733),
                'Lavage, broyage, raffinage, concentration sous vide et stérilisation en boîte.', $output * 0.9, $output * 6);
            $this->deliver($concentrate, $a['conserverie'], $a[$shop], $this->day($date, 10), 'truck', $shares);
        }

        // Milk from Béja to the dairy, cheese to the shops.
        foreach ([['2026-09-10', 1600, 187, 'marsa', [0.8]], ['2026-09-24', 1700, 198, 'tunis', [0.4]]] as [$date, $litres, $output, $shop, $shares]) {
            $milk = $this->farmLot('Lait cru de vache', $date);
            $this->toTransformer($milk, $a['dairy'], $this->at($date, 0, '15:00'), $this->at($date, 0, '16:00'), 'van');
            $cheese = $this->transform($a['dairy'], [[$milk, $litres]], 'Fromage Sicilien de Béja', $output, 'kg',
                $this->day($date, 1), $this->day($date, 22),
                'Pasteurisation, emprésurage, moulage et salage. Affinage court en cave.', $output * 1.5, $output * 4.3);
            $this->deliver($cheese, $a['dairy'], $a[$shop], $this->day($date, 2), 'van', $shares);
        }

        // Fresh and dried produce sent straight from the farm to a shop.
        $direct = [
            // [product, date, shop, mode, shares sold, road distance or null for Haversine]
            ['Abricots de Kairouan', '2026-05-18', 'tunis', 'truck', [0.6, 0.4], null],
            ['Abricots de Kairouan', '2026-06-02', 'sfax', 'truck', [0.7], null],
            ['Amandes de Kairouan', '2025-08-20', 'marsa', 'van', [0.5, 0.3], null],
            ['Amandes de Kairouan', '2026-08-18', 'sfax', 'van', [0.2], null],
            ['Figues de Barbarie', '2026-07-25', 'tunis', 'truck', [0.6, 0.4], null],
            ['Figues de Barbarie', '2026-08-10', 'bizerte', 'truck', [0.5], 330],
            ['Pistaches de Kasserine', '2025-09-12', 'marsa', 'van', [0.4, 0.3], null],
            ['Grenades de Testour', '2025-10-15', 'tunis', 'truck', [0.5, 0.5], null],
            ['Grenades de Testour', '2025-11-02', 'sahel', 'truck', [0.6], null],
            ['Raisins de table Muscat', '2026-07-30', 'bizerte', 'truck', [0.7, 0.3], null],
            ['Raisins de table Muscat', '2026-08-20', 'tunis', 'van', [0.5], null],
            ['Oranges Maltaises', '2025-12-20', 'marsa', 'truck', [0.6, 0.4], null],
            ['Miel de romarin', '2026-04-15', 'bizerte', 'van', [0.3], null],
        ];

        foreach ($direct as [$product, $date, $shop, $mode, $shares, $distance]) {
            $lot = $this->farmLot($product, $date);
            $this->deliver($lot, $this->holder($lot), $a[$shop], $this->day($date, 1), $mode, $shares, $distance);
        }

        // A refused delivery and a lot still on the road.
        $grapes = $this->farmLot('Raisins de table Muscat', '2026-09-05');
        $refused = $this->dispatcher->send($grapes, $this->holder($grapes), $a['sfax'], ['transport_type' => 'truck', 'departure_date' => '2026-09-06 05:00']);
        $this->receptions->rejectDistribution($refused, 'Grappes abîmées pendant le transport, chaîne du froid non respectée.', Carbon::parse('2026-09-06 11:00'));

        $pistachios = $this->farmLot('Pistaches de Kasserine', '2026-09-10');
        $this->dispatcher->send($pistachios, $this->holder($pistachios), $a['marsa'], ['transport_type' => 'van', 'departure_date' => '2026-10-05 08:00']);
    }

    /**
     * Send a lot to a shop, have it received and shelved the next day, then sell parts of it.
     *
     * @param  list<float>  $shares  Fractions of the lot sold, one sale every ten days.
     */
    private function deliver(Lot $lot, User $sender, User $shop, string $day, string $mode, array $shares, ?float $distance = null): Distribution
    {
        $distribution = $this->toDistributor($lot, $sender, $shop, $mode, $day.' 06:00', $day.' 12:00', $distance, $this->day($day, 1).' 09:00');

        $initial = $lot->fresh()->quantity;
        $sold = 0.0;

        foreach ($shares as $index => $share) {
            $at = Carbon::parse($day)->addDays(1 + 10 * ($index + 1))->setTime(17, 30);

            if ($at->isFuture()) {
                break;
            }

            // The last share of a lot sold in full takes exactly what is left.
            $quantity = array_sum($shares) >= 1 && $index === array_key_last($shares)
                ? round($initial - $sold, 2)
                : round($initial * $share, 2);

            $this->sales->record($distribution, $quantity, $at);
            $sold += $quantity;
        }

        return $distribution;
    }

    private function createCertifications(): void
    {
        $hasDocument = Storage::disk(CertificationService::DISK)->exists(self::DEMO_DOCUMENT);

        $rows = [
            // [product, name, type, issuer, number, issued, expires, proof, decision, reason]
            ['Dattes Deglet Nour', 'Agriculture biologique', 'BIO', 'Ecocert', 'TN-BIO-2025-0731', '2025-04-01', '2027-03-31', true, 'approve', null],
            ['Dattes Deglet Nour en coffret', 'Commerce équitable', 'FAIR_TRADE', 'Fair for Life', 'FFL-2025-2290', '2025-06-15', '2027-06-14', true, 'approve', null],
            ['Amandes de Kairouan', 'Agriculture biologique', 'BIO', 'CCPB', 'CCPB-TN-4471', '2026-03-10', '2028-03-09', true, null, null],
            ['Figues de Barbarie', 'Agriculture biologique', 'BIO', 'Ecocert', 'TN-BIO-2026-0215', '2026-01-20', '2028-01-19', true, 'approve', null],
            ['Abricots de Kairouan', 'Agriculture biologique', 'BIO', 'Bio Sud Certification', 'BSC-1180', '2026-02-01', '2027-01-31', false, 'reject', 'Organisme certificateur non reconnu et aucun document fourni.'],
            ['Grenades de Testour', 'Agriculture durable', 'SUSTAINABLE_AGRICULTURE', 'INNORPI', 'INN-2023-871', '2023-09-01', '2026-08-31', true, 'approve', null],
            ['Raisins de table Muscat', 'Produit local du Grand Tunis', 'LOCAL', 'Groupement des viticulteurs de Mornag', 'GVM-2026-014', '2026-05-05', '2028-05-04', true, null, null],
            ['Harissa traditionnelle', 'Sécurité des denrées alimentaires ISO 22000', 'OTHER', 'INNORPI', 'ISO22-TN-3305', '2025-11-12', '2028-11-11', true, 'approve', null],
            ['Semoule de blé dur', 'Agriculture durable', 'SUSTAINABLE_AGRICULTURE', 'INNORPI', 'INN-2026-102', '2026-02-18', '2029-02-17', true, 'approve', null],
            ['Pistaches de Kasserine', 'Produit du terroir', 'OTHER', 'Groupement de développement agricole de Thala', null, '2025-07-01', null, false, null, null],
        ];

        foreach ($rows as [$name, $title, $type, $issuer, $number, $issued, $expires, $proof, $decision, $reason]) {
            $product = Product::with('creator')->where('name', $name)->firstOrFail();

            $certification = $this->certifications->submit($product->creator, $product, [
                'name' => $title,
                'type' => $type,
                'issuing_organization' => $issuer,
                'certificate_number' => $number,
                'issue_date' => $issued,
                'expiration_date' => $expires,
            ]);

            if ($proof && $hasDocument) {
                $certification->forceFill(['document_path' => self::DEMO_DOCUMENT])->save();
            }

            match ($decision) {
                'approve' => $this->certifications->approve($certification, $this->admin),
                'reject' => $this->certifications->reject($certification, $this->admin, $reason),
                default => null,
            };
        }
    }

    private function declareImpacts(): void
    {
        $declarations = [
            ['Dattes Deglet Nour en coffret', ['packaging_co2_kg' => ['value' => 640, 'source' => 'PROVIDED'], 'waste_kg' => ['value' => 310, 'source' => 'MEASURED']]],
            ['Harissa traditionnelle', ['packaging_co2_kg' => ['value' => 210, 'source' => 'PROVIDED'], 'energy_kwh' => ['value' => 468, 'source' => 'MEASURED']]],
            ['Couscous moyen', ['waste_kg' => ['value' => 95, 'source' => 'MEASURED']]],
        ];

        foreach ($declarations as [$product, $declared]) {
            $lot = Lot::with('transformation')->whereHas('product', fn ($query) => $query->where('name', $product))->orderBy('id')->firstOrFail();

            $this->footprint->declare($lot, User::findOrFail($lot->transformation->transformer_id), $declared);
            $this->scores->refresh($lot);
        }
    }

    private function createCommunity(): void
    {
        $comments = [
            5 => ['Très bon produit, et le parcours est limpide.', 'Rien à redire : origine claire et certificat consultable.', 'Excellent, je rachèterai. La fiche du lot donne confiance.', 'Qualité au rendez-vous, et on sait enfin d\'où ça vient.'],
            4 => ['Bon produit. J\'aimerais plus de détails sur l\'emballage.', 'Très correct, la traçabilité est un vrai plus.', 'Bon rapport qualité-prix, parcours bien documenté.'],
            3 => ['Correct, mais il manque des données environnementales.', 'Produit moyen. Le score de transparence pourrait être meilleur.', 'Bien, sans plus. Le transport est long pour ce type de produit.'],
            2 => ['Déçu : des promesses sur l\'étiquette qui ne sont pas prouvées ici.', 'Peu d\'informations vérifiées sur ce lot.'],
        ];

        $consumers = User::where('role', UserRole::CONSOMMATEUR)->where('email', 'like', '%@nutritrace.test')->orderBy('id')->get();

        // One lot per product among those that reached a shop.
        $lots = Lot::publiclyVisible()
            ->whereHas('distributions', fn ($query) => $query->whereNotNull('reception_date'))
            ->orderBy('id')
            ->get()
            ->unique('product_id');

        foreach ($lots as $lot) {
            $ratings = match (true) {
                $lot->trust_score >= 85 => [5, 5, 4, 5],
                $lot->trust_score >= 60 => [4, 4, 3, 5],
                default => [3, 2, 4, 3],
            };

            foreach ($consumers->sortBy(fn () => mt_rand())->take(mt_rand(2, 5)) as $consumer) {
                if (Review::where('user_id', $consumer->id)->where('product_id', $lot->product_id)->exists()) {
                    continue;
                }

                $rating = $ratings[mt_rand(0, count($ratings) - 1)];
                $when = now()->subDays(mt_rand(2, 150))->setTime(mt_rand(9, 21), mt_rand(0, 59));

                $review = new Review(['rating' => $rating, 'comment' => $comments[$rating][mt_rand(0, count($comments[$rating]) - 1)]]);
                $review->user_id = $consumer->id;
                $review->product_id = $lot->product_id;
                $review->lot_id = $lot->id;
                $review->created_at = $when;
                $review->updated_at = $when;
                $review->save();

                $consumer->viewedLots()->syncWithoutDetaching([$lot->id => ['viewed_at' => $when]]);

                if ($rating === 5 && mt_rand(0, 1)) {
                    $consumer->favoriteProducts()->syncWithoutDetaching([$lot->product_id => ['created_at' => $when]]);
                }
            }
        }

        $lotOf = fn (string $product) => $lots->first(fn (Lot $lot) => $lot->product->name === $product);
        $author = fn (int $index) => $consumers[$index % $consumers->count()];

        $reports = [
            // [author, target, type, description, status, response]
            [2, Product::where('name', 'Abricots de Kairouan')->firstOrFail(), ReportType::INVALID_CERTIFICATION, 'Un panneau « bio » est affiché devant ces abricots, mais le certificat indiqué sur la fiche a été refusé.', null, null],
            [3, $lotOf('Figues de Barbarie'), ReportType::SUSPICIOUS_INFORMATION, 'La distance de transport indiquée me paraît très différente d\'un lot à l\'autre pour la même coopérative.', ReportStatus::UNDER_REVIEW, null],
            [4, $lotOf('Dattes Deglet Nour en coffret')?->environmentalImpact, ReportType::MISLEADING_ENVIRONMENTAL_CLAIM, 'L\'emballage individuel de chaque coffret me semble sous-estimé dans le CO₂ déclaré.', ReportStatus::RESOLVED, 'Le transformateur a transmis ses bons de commande d\'emballage : la valeur déclarée correspond aux quantités achetées.'],
            [5, Certification::where('certificate_number', 'INN-2023-871')->first(), ReportType::INVALID_CERTIFICATION, 'Ce certificat d\'agriculture durable est expiré depuis fin août.', ReportStatus::RESOLVED, 'Exact : le certificat est maintenant affiché comme expiré et ne compte plus dans le score du produit.'],
            [6, $lotOf('Raisins de table Muscat'), ReportType::MISLEADING_ENVIRONMENTAL_CLAIM, 'Vendus comme « locaux » alors que le certificat local n\'est pas encore vérifié.', ReportStatus::REJECTED, 'La fiche indique bien que ce certificat est en attente de vérification et il n\'est pas compté comme valide.'],
        ];

        foreach ($reports as [$index, $target, $type, $description, $status, $response]) {
            if (! $target) {
                continue;
            }

            $report = $this->reports->submit($author($index), $target, ['type' => $type->value, 'description' => $description]);

            if ($status) {
                $this->reports->moderate($report, $this->admin, $status, $response);
            }
        }
    }

    /**
     * "2026-05-18" + 1 day at "07:00" => "2026-05-19 07:00".
     */
    private function at(string $date, int $days, string $time): string
    {
        return $this->day($date, $days).' '.$time;
    }

    private function day(string $date, int $days): string
    {
        return Carbon::parse($date)->addDays($days)->toDateString();
    }
}
