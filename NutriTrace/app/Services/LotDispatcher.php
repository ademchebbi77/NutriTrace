<?php

namespace App\Services;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sends a whole lot from its holder to a transformer or a distributor.
 * The lot travels (in transit) until the receiver confirms or refuses it;
 * the holder only changes on confirmation (see LotReceptionService).
 */
class LotDispatcher
{
    public function __construct(
        private readonly GeoDistance $distance,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{transport_type: string, departure_date: string, distance_km?: float|string|null, note?: ?string, destination?: ?string}  $data
     */
    public function send(Lot $lot, User $sender, User $recipient, array $data): LotTransfer|Distribution
    {
        return DB::transaction(function () use ($lot, $sender, $recipient, $data) {
            $transport = $this->createTransport($lot, $sender, $recipient, $data);

            if ($recipient->role === UserRole::DISTRIBUTEUR) {
                $handover = new Distribution(['destination' => $data['destination'] ?? $this->placeLabel($recipient)]);
                $handover->eventAt = $transport->departure_date;
                $handover->forceFill([
                    'lot_id' => $lot->id,
                    'distributor_id' => $recipient->id,
                    'sender_id' => $sender->id,
                    'transport_id' => $transport->id,
                    'latitude' => $recipient->organization?->latitude,
                    'longitude' => $recipient->organization?->longitude,
                    'quantity' => $lot->quantity,
                    'distribution_date' => $transport->departure_date->toDateString(),
                ])->save();
            } else {
                $handover = new LotTransfer(['note' => $data['note'] ?? null]);
                $handover->forceFill([
                    'lot_id' => $lot->id,
                    'from_user_id' => $sender->id,
                    'to_user_id' => $recipient->id,
                    'transport_id' => $transport->id,
                    'quantity' => $lot->quantity,
                ])->save();
            }

            $lot->forceFill(['status' => LotStatus::IN_TRANSIT])->save();

            $this->audit->log('lot.sent', $lot, $lot->lot_number.' -> '.$recipient->displayName());

            return $handover;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTransport(Lot $lot, User $sender, User $recipient, array $data): Transport
    {
        [$originLabel, $originLat, $originLng] = $this->origin($lot, $sender);
        $destination = $recipient->organization;

        $distance = filled($data['distance_km'] ?? null) ? (float) $data['distance_km'] : null;

        // Haversine when both ends are geolocated and no distance was typed in.
        if ($distance === null && $originLat !== null && $destination?->hasCoordinates()) {
            $distance = $this->distance->km($originLat, $originLng, $destination->latitude, $destination->longitude);
        }

        $transport = new Transport([
            'transport_type' => $data['transport_type'],
            'departure_date' => Carbon::parse($data['departure_date']),
            'distance_km' => $distance,
        ]);

        $transport->forceFill([
            'lot_id' => $lot->id,
            'shipper_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'origin_label' => $originLabel,
            'origin_latitude' => $originLat,
            'origin_longitude' => $originLng,
            'destination_label' => $this->placeLabel($recipient),
            'destination_latitude' => $destination?->latitude,
            'destination_longitude' => $destination?->longitude,
            'quantity_transported' => $lot->quantity,
            'unit' => $lot->unit,
        ])->save();

        return $transport;
    }

    /**
     * A farm lot still with its producer leaves from the place of production,
     * any other lot from the sender's organization.
     *
     * @return array{string, ?float, ?float}
     */
    private function origin(Lot $lot, User $sender): array
    {
        $production = $lot->production;

        if ($production && $production->producer_id === $sender->id) {
            return [
                collect([$sender->displayName(), $production->location_city])->filter()->join(', '),
                $production->latitude,
                $production->longitude,
            ];
        }

        return [$this->placeLabel($sender), $sender->organization?->latitude, $sender->organization?->longitude];
    }

    private function placeLabel(User $user): string
    {
        return collect([$user->displayName(), $user->organization?->city])->filter()->join(', ');
    }
}
