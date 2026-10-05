<?php

namespace App\Services;

use App\Enums\EventType;
use App\Enums\LotStatus;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\TraceabilityEvent;
use App\Services\Scoring\LotScoreManager;
use App\Services\Traceability\TraceabilityRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly TraceabilityRecorder $recorder,
        private readonly LotScoreManager $scores,
    ) {}

    /**
     * Record a sale to consumers: a SALE event on the lot and less stock on the shelf.
     * The lot is sold out when nothing is left.
     */
    public function record(Distribution $distribution, float $quantity, ?CarbonInterface $at = null): TraceabilityEvent
    {
        return DB::transaction(function () use ($distribution, $quantity, $at) {
            $lot = Lot::query()->whereKey($distribution->lot_id)->lockForUpdate()->firstOrFail();

            if ($quantity <= 0 || $quantity > $lot->quantity) {
                throw ValidationException::withMessages(['quantity' => __('distributions.sales.validation_quantity', ['max' => $lot->formattedQuantity()])]);
            }

            $remaining = round($lot->quantity - $quantity, 2);

            $lot->forceFill([
                'quantity' => $remaining,
                'status' => $remaining <= 0 ? LotStatus::SOLD_OUT : LotStatus::IN_STORE,
            ])->save();

            $event = $this->recorder->record(
                lot: $lot,
                type: EventType::SALE,
                description: __($remaining <= 0 ? 'events.sale.sold_out' : 'events.sale.sold', [
                    'quantity' => format_quantity($quantity, $lot->unit),
                    'destination' => $distribution->destination,
                ]),
                actor: $distribution->distributor,
                location: $distribution->destination,
                latitude: $distribution->latitude,
                longitude: $distribution->longitude,
                source: $distribution,
                occurredAt: $at,
                metadata: ['quantity' => $quantity, 'remaining' => $remaining],
            );

            $this->scores->refresh($lot);

            return $event;
        });
    }
}
