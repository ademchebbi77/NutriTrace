<?php

namespace Database\Seeders;

use App\Enums\ProductionMethod;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductionService;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Farm productions. Each one creates its lot through ProductionObserver.
     */
    public function run(ProductionService $productions): void
    {
        $rows = [
            // [product, date, quantity, unit, method, expiration, water L, energy kWh, fertilizer kg, pesticide kg]
            ['Olives Chemlali', '2025-11-20', 5000, Unit::KILOGRAM, ProductionMethod::ORGANIC, '2025-12-05', 60000, 180, 0, 0],
            ['Olives Chemlali', '2025-12-10', 3200, Unit::KILOGRAM, ProductionMethod::ORGANIC, '2025-12-25', 38000, 120, 0, 0],
            ['Olives de table Meski', '2025-10-28', 1200, Unit::KILOGRAM, ProductionMethod::ORGANIC, '2026-10-28', 15000, 60, 0, 0],
            ['Lait cru de vache', '2026-09-28', 1800, Unit::LITRE, ProductionMethod::CONVENTIONAL, '2026-10-02', 9000, 210, null, null],
            ['Lait cru de vache', '2026-10-01', 2000, Unit::LITRE, ProductionMethod::CONVENTIONAL, '2026-10-12', 10000, 230, null, null],
            ['Blé dur Karim', '2026-06-25', 12, Unit::TONNE, ProductionMethod::INTEGRATED, '2027-06-25', 0, 950, 480, 12],
            ['Tomates de plein champ', '2026-08-12', 3500, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, '2026-08-26', 210000, 320, 140, 9],
            ['Tomates de plein champ', '2026-09-02', 2800, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, '2026-09-16', 170000, 260, 110, 7],
            ['Oranges Maltaises', '2026-02-15', 4, Unit::TONNE, ProductionMethod::INTEGRATED, '2026-03-30', 240000, 300, 90, 4],
            ['Piments Baklouti', '2026-08-20', 900, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, '2026-09-05', null, null, null, null],
            ['Miel de thym', '2026-07-10', 320, Unit::KILOGRAM, ProductionMethod::ORGANIC, '2028-07-10', 0, 25, 0, 0],
            ['Miel de romarin', '2026-05-22', 280, Unit::KILOGRAM, ProductionMethod::ORGANIC, '2028-05-22', 0, 22, 0, 0],
            ['Lait cru de vache', '2026-09-20', 1500, Unit::LITRE, ProductionMethod::CONVENTIONAL, '2026-09-24', 7500, 175, null, null],
            ['Tomates de plein champ', '2026-07-20', 3000, Unit::KILOGRAM, ProductionMethod::CONVENTIONAL, '2026-08-03', 180000, 280, 120, 8],
            ['Oranges Maltaises', '2026-01-20', 3.5, Unit::TONNE, ProductionMethod::INTEGRATED, '2026-03-05', 210000, 260, 80, 3],
            ['Miel de thym', '2025-07-15', 300, Unit::KILOGRAM, ProductionMethod::ORGANIC, '2027-07-15', 0, 24, 0, 0],
            ['Blé dur Karim', '2025-06-28', 10, Unit::TONNE, ProductionMethod::INTEGRATED, '2026-06-28', 0, 800, 400, 10],
        ];

        $products = Product::with('creator.organization')->get()->keyBy('name');

        foreach ($rows as [$name, $date, $quantity, $unit, $method, $expiration, $water, $energy, $fertilizer, $pesticide]) {
            $product = $products[$name];
            /** @var User $producer */
            $producer = $product->creator;
            $organization = $producer->organization;

            $productions->create($producer, [
                'product_id' => $product->id,
                'location_address' => $organization->address,
                'location_city' => $organization->city,
                'latitude' => $organization->latitude,
                'longitude' => $organization->longitude,
                'production_date' => $date,
                'expiration_date' => $expiration,
                'quantity' => $quantity,
                'unit' => $unit,
                'production_method' => $method,
                'resources_used' => [
                    'water_l' => $water,
                    'energy_kwh' => $energy,
                    'fertilizer_kg' => $fertilizer,
                    'pesticide_kg' => $pesticide,
                ],
            ]);
        }
    }
}
