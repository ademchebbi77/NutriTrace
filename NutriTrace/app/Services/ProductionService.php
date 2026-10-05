<?php

namespace App\Services;

use App\Models\Production;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Record a production. Its initial lot is created by ProductionObserver;
     * the expiration date belongs to the lot and is set here.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $producer, array $data): Production
    {
        return DB::transaction(function () use ($producer, $data) {
            $production = new Production($this->productionAttributes($data));
            $production->producer_id = $producer->id;
            $production->save();

            $production->lot->update(['expiration_date' => $data['expiration_date'] ?? null]);

            return $production;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Production $production, array $data): Production
    {
        return DB::transaction(function () use ($production, $data) {
            $production->update($this->productionAttributes($data));

            $production->lot->update(['expiration_date' => $data['expiration_date'] ?? null]);

            return $production;
        });
    }

    public function delete(Production $production): void
    {
        DB::transaction(function () use ($production) {
            $production->lot?->delete();
            $production->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function productionAttributes(array $data): array
    {
        $resources = array_filter($data['resources_used'] ?? [], fn ($value) => $value !== null && $value !== '');

        return [
            ...collect($data)->except(['expiration_date', 'resources_used'])->all(),
            'resources_used' => $resources ?: null,
        ];
    }
}
