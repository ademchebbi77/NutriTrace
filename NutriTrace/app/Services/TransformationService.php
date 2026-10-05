<?php

namespace App\Services;

use App\Enums\LotStatus;
use App\Models\Lot;
use App\Models\Transformation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationService
{
    /**
     * Turn one or several source lots into a new lot of another product.
     * Source quantities are consumed; the output lot starts its own chain and
     * stays linked to its sources, so the journey can be traced back to the farms.
     *
     * @param  array{output_product_id: int, output_quantity: float|string, output_unit: string, transformation_date: string, expiration_date?: ?string, process_description: string, energy_used_kwh?: mixed, water_used_l?: mixed, inputs: list<array{lot_id: int|string, quantity_used: float|string}>}  $data
     */
    public function create(User $transformer, array $data): Transformation
    {
        return DB::transaction(function () use ($transformer, $data) {
            $inputs = collect($data['inputs'])->keyBy('lot_id');

            // Lock the source lots so two transformations cannot consume the same stock.
            $lots = Lot::query()->whereKey($inputs->keys())->lockForUpdate()->get()->keyBy('id');

            foreach ($inputs as $lotId => $input) {
                $lot = $lots->get($lotId);

                if (! $lot || ! $lot->isHeldBy($transformer) || ! $lot->isAvailable() || (float) $input['quantity_used'] > $lot->quantity) {
                    throw ValidationException::withMessages(['inputs' => __('transformations.validation.input_unavailable')]);
                }
            }

            $output = new Lot(['expiration_date' => $data['expiration_date'] ?? null]);
            $output->forceFill([
                'product_id' => $data['output_product_id'],
                'current_holder_id' => $transformer->id,
                'quantity' => $data['output_quantity'],
                'unit' => $data['output_unit'],
                'production_date' => $data['transformation_date'],
                'status' => LotStatus::CREATED,
            ])->save();

            $organization = $transformer->organization;

            $transformation = new Transformation([
                'transformation_date' => $data['transformation_date'],
                'process_description' => $data['process_description'],
                'energy_used_kwh' => filled($data['energy_used_kwh'] ?? null) ? $data['energy_used_kwh'] : null,
                'water_used_l' => filled($data['water_used_l'] ?? null) ? $data['water_used_l'] : null,
            ]);

            $transformation->forceFill([
                'transformer_id' => $transformer->id,
                'output_lot_id' => $output->id,
                'location_label' => collect([$transformer->displayName(), $organization?->city])->filter()->join(', '),
                'latitude' => $organization?->latitude,
                'longitude' => $organization?->longitude,
                'input_quantity' => $inputs->sum(fn (array $input) => (float) $input['quantity_used']),
                'input_unit' => $lots->first()->unit,
                'output_quantity' => $data['output_quantity'],
            ])->save();

            foreach ($inputs as $lotId => $input) {
                $lot = $lots->get($lotId);
                $remaining = round($lot->quantity - (float) $input['quantity_used'], 2);

                $lot->forceFill([
                    'quantity' => $remaining,
                    'status' => $remaining <= 0 ? LotStatus::SOLD_OUT : LotStatus::IN_TRANSFORMATION,
                ])->save();

                $transformation->inputs()->create([
                    'lot_id' => $lot->id,
                    'quantity_used' => $input['quantity_used'],
                ]);
            }

            return $transformation->setRelation('outputLot', $output);
        });
    }
}
