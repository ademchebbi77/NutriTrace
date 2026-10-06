<?php

namespace Tests\Concerns;

use App\Enums\ProductionMethod;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Production;
use App\Models\Transformation;
use App\Models\User;
use App\Services\LotDispatcher;
use App\Services\LotReceptionService;
use App\Services\TransformationService;
use Illuminate\Support\Carbon;

/**
 * Builds the reference journey of the brief through the real services:
 * 5 000 kg of olives in Sfax -> 900 L of oil -> Sfax to Tunis by truck (270 km) -> store.
 */
trait BuildsJourneys
{
    protected User $producer;

    protected User $transformer;

    protected User $distributor;

    protected Lot $oliveLot;

    protected Lot $oilLot;

    protected Transformation $transformation;

    protected Distribution $distribution;

    protected function actor(string $role, string $city, bool $verified = true): User
    {
        $user = User::factory()->create(['role' => $role]);

        Organization::factory()->inCity($city)->create(['user_id' => $user->id, 'is_verified' => $verified]);

        return $user->load('organization');
    }

    protected function createActors(): void
    {
        $this->producer = $this->actor('PRODUCTEUR', 'Sfax');
        $this->transformer = $this->actor('TRANSFORMATEUR', 'Sfax');
        $this->distributor = $this->actor('DISTRIBUTEUR', 'Tunis');
    }

    protected function createOliveLot(float $quantity = 5000, string $date = '2025-11-20'): Lot
    {
        return Production::factory()->by($this->producer)->create([
            'location_city' => 'Sfax',
            'latitude' => 34.7406,
            'longitude' => 10.7603,
            'production_date' => $date,
            'quantity' => $quantity,
            'production_method' => ProductionMethod::CONVENTIONAL,
            'resources_used' => ['water_l' => 60000, 'energy_kwh' => 180],
        ])->lot;
    }

    /**
     * Producer sends the lot to the transformer, who accepts it.
     */
    protected function transferToTransformer(Lot $lot, string $departure = '2025-11-21 08:00', string $arrival = '2025-11-21 10:00'): LotTransfer
    {
        $transfer = app(LotDispatcher::class)->send($lot, $this->producer, $this->transformer, [
            'transport_type' => 'truck',
            'departure_date' => $departure,
        ]);

        app(LotReceptionService::class)->acceptTransfer($transfer, Carbon::parse($arrival));

        return $transfer;
    }

    protected function transform(Lot $source, float $used = 5000, float $output = 900, string $date = '2025-11-22'): Transformation
    {
        $product = Product::factory()->create(['created_by' => $this->transformer->id, 'name' => 'Huile d\'olive extra vierge']);

        return app(TransformationService::class)->create($this->transformer, [
            'output_product_id' => $product->id,
            'output_quantity' => $output,
            'output_unit' => 'L',
            'transformation_date' => $date,
            'expiration_date' => '2027-11-22',
            'process_description' => 'Trituration et extraction à froid.',
            'energy_used_kwh' => 450,
            'water_used_l' => 2500,
            'inputs' => [['lot_id' => $source->id, 'quantity_used' => $used]],
        ]);
    }

    /**
     * Transformer sends the lot to the distributor (270 km by truck), who receives it and puts it in store.
     */
    protected function distribute(Lot $lot, bool $inStore = true): Distribution
    {
        $distribution = app(LotDispatcher::class)->send($lot, $this->transformer, $this->distributor, [
            'transport_type' => 'truck',
            'departure_date' => '2025-12-01 07:00',
            'distance_km' => 270,
        ]);

        $receptions = app(LotReceptionService::class);
        $receptions->receiveDistribution($distribution, null, Carbon::parse('2025-12-01 12:00'));

        if ($inStore) {
            $receptions->markInStore($distribution->fresh(), Carbon::parse('2025-12-02 09:00'));
        }

        return $distribution->fresh();
    }

    /**
     * The whole reference journey, from the olive grove to the shelf.
     */
    protected function buildOliveOilJourney(): void
    {
        $this->createActors();

        $this->oliveLot = $this->createOliveLot();
        $this->transferToTransformer($this->oliveLot);

        $this->transformation = $this->transform($this->oliveLot->fresh());
        $this->oilLot = $this->transformation->outputLot;

        $this->distribution = $this->distribute($this->oilLot);

        $this->oliveLot = $this->oliveLot->fresh();
        $this->oilLot = $this->oilLot->fresh();
    }
}
