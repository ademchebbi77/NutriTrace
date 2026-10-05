<?php

namespace App\Observers;

use App\Enums\EventType;
use App\Enums\TransportStatus;
use App\Models\Transport;
use App\Services\Scoring\LotScoreManager;
use App\Services\Traceability\TraceabilityRecorder;

class TransportObserver
{
    public function __construct(
        private readonly TraceabilityRecorder $recorder,
        private readonly LotScoreManager $scores,
    ) {}

    /**
     * Departure of the lot.
     */
    public function created(Transport $transport): void
    {
        $this->recorder->record(
            lot: $transport->lot,
            type: EventType::TRANSPORT,
            description: __('events.transport.departure', [
                'destination' => $transport->destination_label,
                'mode' => mb_strtolower($transport->transport_type->label()),
                'distance' => $transport->distance_km === null ? '?' : number_format($transport->distance_km, 0, ',', ' '),
            ]),
            actor: $transport->shipper,
            location: $transport->origin_label,
            latitude: $transport->origin_latitude,
            longitude: $transport->origin_longitude,
            source: $transport,
            occurredAt: $transport->departure_date,
            metadata: ['step' => 'departure', 'mode' => $transport->transport_type->value, 'distance_km' => $transport->distance_km],
        );

        $this->scores->refresh($transport->lot);
    }

    /**
     * Arrival, or return to the shipper when the receiver refused the lot.
     */
    public function updated(Transport $transport): void
    {
        if ($transport->wasChanged('status')) {
            match ($transport->status) {
                TransportStatus::DELIVERED => $this->recorder->record(
                    lot: $transport->lot,
                    type: EventType::TRANSPORT,
                    description: __('events.transport.arrival', ['destination' => $transport->destination_label]),
                    actor: $transport->recipient,
                    location: $transport->destination_label,
                    latitude: $transport->destination_latitude,
                    longitude: $transport->destination_longitude,
                    source: $transport,
                    occurredAt: $transport->arrival_date,
                    metadata: ['step' => 'arrival'],
                ),
                TransportStatus::CANCELLED => $this->recorder->record(
                    lot: $transport->lot,
                    type: EventType::TRANSPORT,
                    description: __('events.transport.cancelled', ['destination' => $transport->destination_label]),
                    actor: $transport->recipient,
                    location: $transport->origin_label,
                    latitude: $transport->origin_latitude,
                    longitude: $transport->origin_longitude,
                    source: $transport,
                    metadata: ['step' => 'cancelled'],
                ),
                default => null,
            };
        } elseif ($transport->wasChanged(['distance_km', 'transport_type'])) {
            // A corrected mode or distance is a new event: the departure event is never rewritten.
            $this->recorder->record(
                lot: $transport->lot,
                type: EventType::TRANSPORT,
                description: __('events.transport.corrected', [
                    'mode' => mb_strtolower($transport->transport_type->label()),
                    'distance' => number_format((float) $transport->distance_km, 0, ',', ' '),
                ]),
                actor: $transport->shipper,
                location: $transport->origin_label,
                latitude: $transport->origin_latitude,
                longitude: $transport->origin_longitude,
                source: $transport,
                metadata: ['step' => 'correction', 'mode' => $transport->transport_type->value, 'distance_km' => $transport->distance_km],
            );
        }

        $this->scores->refresh($transport->lot);
    }
}
