<?php

namespace App\Observers;

use App\Enums\EventType;
use App\Models\Transformation;
use App\Services\Traceability\TraceabilityRecorder;

class TransformationObserver
{
    public function __construct(private readonly TraceabilityRecorder $recorder) {}

    /**
     * First event of the output lot's chain.
     */
    public function created(Transformation $transformation): void
    {
        $transformation->loadMissing(['outputLot.product', 'transformer']);
        $lot = $transformation->outputLot;

        $this->recorder->record(
            lot: $lot,
            type: EventType::TRANSFORMATION,
            description: __('events.transformation.output', [
                'quantity' => format_quantity($transformation->output_quantity, $lot->unit),
                'product' => $lot->product->name,
            ]),
            actor: $transformation->transformer,
            location: $transformation->location_label,
            latitude: $transformation->latitude,
            longitude: $transformation->longitude,
            source: $transformation,
            occurredAt: event_moment($transformation->transformation_date),
            metadata: ['output_quantity' => $transformation->output_quantity, 'unit' => $lot->unit->value],
        );
    }
}
