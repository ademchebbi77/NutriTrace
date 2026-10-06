<?php

namespace Database\Seeders\Concerns;

use App\Models\Distribution;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Helpers shared by the seeders that move lots through the chain with the real services.
 * The seeder using this trait must expose $dispatcher, $receptions, $transformations and $sales.
 */
trait DrivesTheChain
{
    protected function farmLot(string $product, string $date): Lot
    {
        return Production::query()
            ->whereDate('production_date', $date)
            ->whereHas('product', fn ($query) => $query->where('name', $product))
            ->firstOrFail()
            ->lot;
    }

    protected function holder(Lot $lot): User
    {
        return User::with('organization')->findOrFail($lot->current_holder_id);
    }

    protected function toTransformer(Lot $lot, User $transformer, string $departure, string $arrival, string $mode = 'truck'): void
    {
        /** @var LotTransfer $transfer */
        $transfer = $this->dispatcher->send($lot, $this->holder($lot), $transformer, [
            'transport_type' => $mode,
            'departure_date' => $departure,
        ]);

        $this->receptions->acceptTransfer($transfer, Carbon::parse($arrival));
    }

    /**
     * @param  list<array{Lot, float}>  $inputs
     */
    protected function transform(User $transformer, array $inputs, string $product, float $quantity, string $unit, string $date, string $expiration, string $process, float $energy, float $water): Lot
    {
        return $this->transformations->create($transformer, [
            'output_product_id' => Product::where('name', $product)->firstOrFail()->id,
            'output_quantity' => $quantity,
            'output_unit' => $unit,
            'transformation_date' => $date,
            'expiration_date' => $expiration,
            'process_description' => $process,
            'energy_used_kwh' => $energy,
            'water_used_l' => $water,
            'inputs' => array_map(fn (array $input) => ['lot_id' => $input[0]->id, 'quantity_used' => $input[1]], $inputs),
        ])->outputLot;
    }

    protected function toDistributor(Lot $lot, User $sender, User $distributor, string $mode, string $departure, string $arrival, ?float $distance, ?string $inStore): Distribution
    {
        /** @var Distribution $distribution */
        $distribution = $this->dispatcher->send($lot->fresh(), $sender, $distributor, [
            'transport_type' => $mode,
            'departure_date' => $departure,
            'distance_km' => $distance,
        ]);

        $this->receptions->receiveDistribution($distribution, null, Carbon::parse($arrival));

        if ($inStore) {
            $this->receptions->markInStore($distribution->fresh(), Carbon::parse($inStore));
        }

        return $distribution->fresh();
    }

    /**
     * @param  list<array{string, float}>  $sales
     */
    protected function sell(Distribution $distribution, array $sales): void
    {
        foreach ($sales as [$at, $quantity]) {
            $this->sales->record($distribution, $quantity, Carbon::parse($at));
        }
    }
}
