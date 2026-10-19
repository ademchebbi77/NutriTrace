<?php

namespace App\Services\Ai;

use App\Models\Production;
use App\Models\User;

/**
 * Builds the payload sent to POST /production/forecast on the Python service.
 *
 * Only the current producer's own productions of the same product and unit are
 * included — we never mix units (kg, t, L, unit) in one forecast request.
 */
class ProductionForecastContext
{
    /**
     * Return the validated records and unit for a forecast centred on $production.
     * Returns null if the producer has no valid historical records for that product/unit.
     *
     * The chart, statistics and forecast must all share this same validated
     * dataset, so filtering happens here, before the payload is sent.
     *
     * @return array{records: list<array<string, mixed>>, unit: string}|null
     */
    public function forProduction(Production $production): ?array
    {
        $unit = $production->unit->value;

        $history = Production::where('producer_id', $production->producer_id)
            ->where('product_id', $production->product_id)
            ->where('unit', $unit)
            ->orderBy('production_date')
            ->orderBy('id')
            ->get(['production_date', 'quantity', 'unit', 'production_method']);

        $records = [];
        $seen = [];

        foreach ($history as $p) {
            $quantity = (float) $p->quantity;

            if (! is_finite($quantity) || $quantity <= 0) {
                continue;
            }

            // Ignore exact duplicates (same date, quantity and method):
            // they are usually double entries and would distort the statistics.
            $key = $p->production_date->toDateString().'|'.$quantity.'|'.$p->production_method->value;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $records[] = [
                'production_date' => $p->production_date->toDateString(),
                'quantity'        => $quantity,
                'unit'            => $p->unit->value,
                'production_method' => $p->production_method->value,
            ];
        }

        if ($records === []) {
            return null;
        }

        // The Python endpoint accepts at most 500 records: keep the most recent ones.
        if (count($records) > 500) {
            $records = array_slice($records, -500);
        }

        return [
            'records' => $records,
            'unit'    => $unit,
        ];
    }
}
