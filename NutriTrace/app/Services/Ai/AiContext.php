<?php

namespace App\Services\Ai;

use App\Http\Resources\TraceResource;
use App\Models\Lot;
use App\Models\Transport;
use App\Services\LotInsightsBuilder;
use Illuminate\Support\Arr;

/**
 * Data handed to the local AI service. The model answers only from this, so it
 * holds the public view of the lot (same as TraceResource: no contact details)
 * plus the details of every transport of the journey.
 *
 * The same structure is exported for training (php artisan ai:export): keep
 * ai/nutritrace_ai/context.py in step when changing it.
 */
class AiContext
{
    public function __construct(private readonly LotInsightsBuilder $insights) {}

    /**
     * @return array<string, mixed>
     */
    public function forLot(Lot $lot): array
    {
        $insights = $this->insights->for($lot);
        $trace = (new TraceResource($insights))->resolve();

        return [
            'lot' => Arr::only($trace['lot'], ['number', 'status_label', 'quantity', 'initial_quantity', 'production_date', 'expiration_date'])
                + ['unit' => $lot->unit->symbol()],
            'product' => Arr::only($trace['product'], ['name', 'category', 'origin']),
            'journey' => array_map(
                fn (array $event) => Arr::only($event, ['type', 'lot', 'occurred_at', 'description', 'location', 'actor']),
                $trace['journey'],
            ),
            'source_lots' => $trace['source_lots'],
            'transports' => $insights->journey->transports()->map(fn (Transport $transport) => $this->forTransport($transport))->all(),
            'chain' => $trace['chain'],
            'environmental_impact' => $trace['environmental_impact']
                ? Arr::only($trace['environmental_impact'], ['grade', 'score', 'food_miles_km', 'co2_per_kg'])
                : null,
            'local' => $trace['local'],
            'certifications' => array_map(
                fn (array $certification) => Arr::only($certification, ['name', 'valid', 'issuing_organization', 'expiration_date']),
                $trace['certifications'],
            ),
            'transparency' => Arr::only($trace['transparency'], ['score', 'level']),
            'warnings' => array_map(fn (array $warning) => Arr::only($warning, ['severity', 'message']), $trace['warnings']),
            'under_investigation' => $trace['under_investigation'],
        ];
    }

    /**
     * One transport, as used both in a lot's context and for the anomaly check.
     *
     * @return array<string, mixed>
     */
    public function forTransport(Transport $transport): array
    {
        return [
            'lot' => $transport->lot->lot_number,
            'production_date' => $transport->lot->production_date->toDateString(),
            'mode' => $transport->transport_type->value,
            'mode_label' => $transport->transport_type->label(),
            'status' => $transport->status->value,
            'status_label' => $transport->status->label(),
            'origin' => $transport->origin_label,
            'origin_latitude' => $transport->origin_latitude,
            'origin_longitude' => $transport->origin_longitude,
            'destination' => $transport->destination_label,
            'destination_latitude' => $transport->destination_latitude,
            'destination_longitude' => $transport->destination_longitude,
            'departure_date' => $transport->departure_date->toIso8601String(),
            'arrival_date' => $transport->arrival_date?->toIso8601String(),
            'distance_km' => $transport->distance_km,
            'quantity' => $transport->quantity_transported,
            'unit' => $transport->unit->symbol(),
            'mass_kg' => $transport->massKg(),
            'co2_kg' => ($co2 = $transport->co2Kg()) === null ? null : round($co2, 2),
        ];
    }

    /**
     * Fingerprint of the data the AI saw: a cached answer is reused only for the same data.
     *
     * @param  array<string, mixed>  $context
     */
    public function version(array $context): string
    {
        return hash('sha256', json_encode($context));
    }
}
