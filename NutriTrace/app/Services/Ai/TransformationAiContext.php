<?php

namespace App\Services\Ai;

use App\Models\Lot;
use App\Models\Product;
use App\Models\Transformation;
use App\Models\TransformationInput;

/**
 * Data handed to the local AI for the Transformation - Lots module (raw data; features
 * are computed on the Python side, ai/nutritrace_ai/transformations.py and shelf_life.py).
 *
 * A transformation gives the same payload whether it comes from the form (before saving)
 * or from the database (afterwards), so the check made on the form is found again once
 * the transformation is saved.
 */
class TransformationAiContext
{
    /**
     * @param  array<string, mixed>  $data  Validated TransformationRequest data.
     * @return array<string, mixed>
     */
    public function fromForm(array $data): array
    {
        $lots = Lot::with('product.category')->whereKey(collect($data['inputs'])->pluck('lot_id'))->get()->keyBy('id');
        $product = Product::with('category')->find($data['output_product_id']);

        return $this->payload(
            date: (string) $data['transformation_date'],
            inputs: collect($data['inputs'])->map(fn (array $input) => [$lots[$input['lot_id']], (float) $input['quantity_used']])->all(),
            product: $product,
            quantity: (float) $data['output_quantity'],
            unit: (string) $data['output_unit'],
            expiration: $data['expiration_date'] ?? null,
            energy: $data['energy_used_kwh'] ?? null,
            water: $data['water_used_l'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function fromTransformation(Transformation $transformation): array
    {
        $transformation->loadMissing(['inputs.lot.product.category', 'outputLot.product.category']);
        $output = $transformation->outputLot;

        return ['id' => $transformation->id] + $this->payload(
            date: $transformation->transformation_date->toDateString(),
            inputs: $transformation->inputs->map(fn (TransformationInput $input) => [$input->lot, (float) $input->quantity_used])->all(),
            product: $output->product,
            quantity: (float) $output->initial_quantity,
            unit: $output->unit->value,
            expiration: $output->expiration_date?->toDateString(),
            energy: $transformation->energy_used_kwh,
            water: $transformation->water_used_l,
        );
    }

    /**
     * A lot and its date limit, for the shelf-life check of the admin overview.
     *
     * @return array<string, mixed>
     */
    public function forLotExpiry(Lot $lot): array
    {
        return [
            'id' => $lot->id,
            'lot' => $lot->lot_number,
            'product' => $lot->product->name,
            'category' => $lot->product->category?->name,
            'unit' => $lot->unit->value === 'unit' ? 'unité' : $lot->unit->value,
            'transformed' => $lot->production_id === null,
            'production_date' => $lot->production_date->toDateString(),
            'expiration_date' => $lot->expiration_date?->toDateString(),
        ];
    }

    /**
     * Fingerprint of the data the AI saw (the database id is not part of it).
     *
     * @param  array<string, mixed>  $payload
     */
    public function version(array $payload): string
    {
        unset($payload['id']);

        return hash('sha256', json_encode($payload));
    }

    /**
     * @param  list<array{0: Lot, 1: float}>  $inputs
     * @return array<string, mixed>
     */
    private function payload(string $date, array $inputs, ?Product $product, float $quantity, string $unit, ?string $expiration, mixed $energy, mixed $water): array
    {
        $inputs = collect($inputs)->sortBy(fn (array $input) => $input[0]->id)->values();

        return [
            'transformation_date' => substr($date, 0, 10),
            'inputs' => $inputs->map(fn (array $input) => [
                'lot' => $input[0]->lot_number,
                'product' => $input[0]->product->name,
                'category' => $input[0]->product->category?->name,
                'unit' => $input[0]->unit->value,
                'quantity_used' => round($input[1], 2),
                'production_date' => $input[0]->production_date->toDateString(),
                'expiration_date' => $input[0]->expiration_date?->toDateString(),
            ])->all(),
            'output' => [
                'product' => $product?->name,
                'category' => $product?->category?->name,
                'unit' => $unit,
                'quantity' => round($quantity, 2),
                'expiration_date' => $expiration ? substr((string) $expiration, 0, 10) : null,
            ],
            'energy_kwh' => filled($energy) ? round((float) $energy, 2) : null,
            'water_l' => filled($water) ? round((float) $water, 2) : null,
        ];
    }
}
