<?php

namespace App\Observers;

use App\Enums\EventType;
use App\Enums\LotStatus;
use App\Models\Lot;
use App\Models\Production;
use App\Services\Scoring\LotScoreManager;
use App\Services\Traceability\TraceabilityRecorder;

class ProductionObserver
{
    public function __construct(
        private readonly TraceabilityRecorder $recorder,
        private readonly LotScoreManager $scores,
    ) {}

    /**
     * Every production gives birth to its initial lot, held by the producer,
     * and to the first event of that lot's chain.
     */
    public function created(Production $production): void
    {
        $lot = new Lot;

        $lot->forceFill([
            'product_id' => $production->product_id,
            'production_id' => $production->id,
            'current_holder_id' => $production->producer_id,
            'quantity' => $production->quantity,
            'unit' => $production->unit,
            'production_date' => $production->production_date,
            'status' => LotStatus::CREATED,
        ])->save();

        $production->setRelation('lot', $lot);

        $this->record($production, $lot, 'events.production.created');
        $this->scores->refresh($lot);
    }

    /**
     * Keep the untouched initial lot in line with a corrected production.
     * The correction is a new event: the original one is never rewritten.
     */
    public function updated(Production $production): void
    {
        $lot = $production->lot;

        if (! $lot) {
            return;
        }

        if ($production->wasChanged(['product_id', 'quantity', 'unit', 'production_date'])) {
            $lot->forceFill([
                'product_id' => $production->product_id,
                'initial_quantity' => $production->quantity,
                'quantity' => $production->quantity,
                'unit' => $production->unit,
                'production_date' => $production->production_date,
            ])->save();
        }

        $this->record($production, $lot, 'events.production.corrected');
        $this->scores->refresh($lot);
    }

    private function record(Production $production, Lot $lot, string $message): void
    {
        $production->loadMissing(['product', 'producer']);

        $this->recorder->record(
            lot: $lot,
            type: EventType::PRODUCTION,
            description: __($message, [
                'quantity' => format_quantity($production->quantity, $production->unit),
                'product' => $production->product->name,
                'method' => mb_strtolower($production->production_method->label()),
            ]),
            actor: $production->producer,
            location: collect([$production->location_address, $production->location_city])->filter()->join(', '),
            latitude: $production->latitude,
            longitude: $production->longitude,
            source: $production,
            occurredAt: event_moment($production->production_date),
            metadata: ['quantity' => $production->quantity, 'unit' => $production->unit->value, 'method' => $production->production_method->value],
        );
    }
}
