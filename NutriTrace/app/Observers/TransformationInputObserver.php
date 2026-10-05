<?php

namespace App\Observers;

use App\Enums\EventType;
use App\Models\TransformationInput;
use App\Services\Scoring\LotScoreManager;
use App\Services\Traceability\TraceabilityRecorder;

class TransformationInputObserver
{
    public function __construct(
        private readonly TraceabilityRecorder $recorder,
        private readonly LotScoreManager $scores,
    ) {}

    /**
     * The source lot records that part of it went into a transformation;
     * the output lot's scores now include this source.
     */
    public function created(TransformationInput $input): void
    {
        $input->loadMissing(['lot', 'transformation.outputLot.product', 'transformation.transformer']);
        $transformation = $input->transformation;

        $this->recorder->record(
            lot: $input->lot,
            type: EventType::TRANSFORMATION,
            description: __('events.transformation.input', [
                'quantity' => format_quantity($input->quantity_used, $input->lot->unit),
                'product' => $transformation->outputLot->product->name,
                'lot' => $transformation->outputLot->lot_number,
            ]),
            actor: $transformation->transformer,
            location: $transformation->location_label,
            latitude: $transformation->latitude,
            longitude: $transformation->longitude,
            source: $transformation,
            occurredAt: event_moment($transformation->transformation_date),
            metadata: ['quantity_used' => $input->quantity_used, 'output_lot' => $transformation->outputLot->lot_number],
        );

        $this->scores->refresh($input->lot);
    }
}
