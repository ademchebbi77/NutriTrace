<?php

namespace App\Services;

use App\Models\Production;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $producer, array $data): Production
    {
        return DB::transaction(function () use ($producer, $data) {
            $production = new Production($this->productionAttributes($data));
            $production->producer_id = $producer->id;
            $production->save();

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

            return $production;
        });
    }

    public function delete(Production $production): void
    {
        DB::transaction(function () use ($production) {
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
