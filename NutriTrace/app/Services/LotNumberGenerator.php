<?php

namespace App\Services;

use App\Models\Lot;

class LotNumberGenerator
{
    /**
     * Next lot number of the year, e.g. LOT-2026-001. Numbers are sequential per year;
     * the unique index on lots.lot_number is the final guard against duplicates.
     */
    public function next(int $year): string
    {
        $prefix = "LOT-{$year}-";

        $last = Lot::query()
            ->where('lot_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(lot_number) DESC')
            ->orderByDesc('lot_number')
            ->lockForUpdate()
            ->value('lot_number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }
}
