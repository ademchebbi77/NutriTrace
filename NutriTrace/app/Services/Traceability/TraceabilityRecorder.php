<?php

namespace App\Services\Traceability;

use App\Enums\EventType;
use App\Models\Lot;
use App\Models\TraceabilityEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for writing traceability events. Each event stores the hash of
 * the previous event of the same lot, so any later change breaks the chain.
 */
class TraceabilityRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Lot $lot,
        EventType $type,
        string $description,
        ?User $actor = null,
        ?string $location = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?Model $source = null,
        ?CarbonInterface $occurredAt = null,
        array $metadata = [],
    ): TraceabilityEvent {
        // Round-trip through JSON so value types are the same now and when read back for verification.
        $metadata = json_decode(json_encode($metadata), true);

        return DB::transaction(function () use ($lot, $type, $description, $actor, $location, $latitude, $longitude, $source, $occurredAt, $metadata) {
            $previousHash = TraceabilityEvent::query()
                ->where('lot_id', $lot->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('hash') ?? TraceabilityEvent::GENESIS_HASH;

            $event = new TraceabilityEvent([
                'lot_id' => $lot->id,
                'event_type' => $type,
                'actor_id' => $actor?->id,
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'occurred_at' => ($occurredAt ?? now())->copy()->startOfSecond(),
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'metadata' => $metadata ?: null,
                'previous_hash' => $previousHash,
            ]);

            $event->hash = self::hash($event);
            $event->save();

            return $event;
        });
    }

    /**
     * SHA-256 over every meaningful field of the event, in a fixed order.
     */
    public static function hash(TraceabilityEvent $event): string
    {
        $metadata = $event->metadata;

        if (is_array($metadata)) {
            ksort($metadata);
        }

        return hash('sha256', json_encode([
            $event->previous_hash,
            (int) $event->lot_id,
            $event->event_type->value,
            $event->actor_id === null ? null : (int) $event->actor_id,
            $event->location,
            $event->latitude === null ? null : number_format($event->latitude, 7, '.', ''),
            $event->longitude === null ? null : number_format($event->longitude, 7, '.', ''),
            $event->occurred_at->format('Y-m-d H:i:s'),
            $event->description,
            $event->source_type,
            $event->source_id === null ? null : (int) $event->source_id,
            $metadata ?: null,
        ], JSON_UNESCAPED_UNICODE));
    }
}
