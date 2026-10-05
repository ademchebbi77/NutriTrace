<?php

namespace App\Http\Resources;

use App\Models\Certification;
use App\Models\EnvironmentalImpact;
use App\Models\TraceabilityEvent;
use App\Services\LotInsights;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public, read-only view of a lot. Organizations appear by name and city only:
 * no email, phone, address or registration number.
 *
 * @property LotInsights $resource
 */
class TraceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $insights = $this->resource;
        $lot = $insights->lot;
        $impact = $insights->impact;

        return [
            'lot' => [
                'number' => $lot->lot_number,
                'status' => $lot->status->value,
                'status_label' => $lot->status->label(),
                'quantity' => $lot->quantity,
                'initial_quantity' => $lot->initial_quantity,
                'unit' => $lot->unit->value,
                'production_date' => $lot->production_date->toDateString(),
                'expiration_date' => $lot->expiration_date?->toDateString(),
                'url' => $lot->publicUrl(),
            ],
            'product' => [
                'name' => $lot->product->name,
                'category' => $lot->product->category->name,
                'origin' => $lot->product->origin,
                'barcode' => $lot->product->barcode,
            ],
            'journey' => $insights->journey->allEvents()->map(fn (TraceabilityEvent $event) => [
                'type' => $event->event_type->value,
                'lot' => $event->lot->lot_number,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'description' => $event->description,
                'location' => $event->location,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'actor' => $event->actorName(),
                'hash' => $event->hash,
            ])->all(),
            'source_lots' => $insights->journey->lots()->reject->is($lot)->pluck('lot_number')->values()->all(),
            'chain' => [
                'valid' => $insights->chain->valid,
                'events' => $insights->chain->events,
            ],
            'environmental_impact' => $impact ? [
                'grade' => $impact->grade,
                'score' => $impact->score,
                'food_miles_km' => $impact->food_miles_km,
                'co2_per_kg' => $insights->co2PerKg(),
                'indicators' => collect(EnvironmentalImpact::INDICATORS)->mapWithKeys(fn (string $indicator) => [
                    $indicator => ['value' => $impact->{$indicator}, 'source' => $impact->source($indicator)?->value],
                ])->all(),
            ] : null,
            'local' => $insights->local,
            'certifications' => $insights->certifications->map(fn (Certification $certification) => [
                'name' => $certification->name,
                'type' => $certification->type->value,
                'status' => $certification->status->value,
                'valid' => $certification->isValid(),
                'issuing_organization' => $certification->issuing_organization,
                'certificate_number' => $certification->certificate_number,
                'issue_date' => $certification->issue_date->toDateString(),
                'expiration_date' => $certification->expiration_date?->toDateString(),
            ])->all(),
            'transparency' => [
                'score' => $insights->trust->score,
                'level' => $insights->trust->level(),
                'components' => $insights->trust->components,
                'penalties' => $insights->trust->penalties,
            ],
            'warnings' => $insights->warnings->all(),
            'under_investigation' => $insights->underInvestigation,
        ];
    }
}
