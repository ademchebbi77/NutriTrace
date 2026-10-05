<?php

namespace App\Observers;

use App\Enums\DistributionStatus;
use App\Enums\EventType;
use App\Models\Distribution;
use App\Services\Scoring\LotScoreManager;
use App\Services\Traceability\TraceabilityRecorder;
use Carbon\CarbonInterface;

class DistributionObserver
{
    public function __construct(
        private readonly TraceabilityRecorder $recorder,
        private readonly LotScoreManager $scores,
    ) {}

    public function created(Distribution $distribution): void
    {
        $distribution->loadMissing(['distributor.organization', 'sender']);

        $this->record($distribution, 'events.distribution.shipped', $distribution->sender, $distribution->eventAt ?? event_moment($distribution->distribution_date));
        $this->scores->refresh($distribution->lot);
    }

    public function updated(Distribution $distribution): void
    {
        if ($distribution->wasChanged('status')) {
            $distribution->loadMissing('distributor.organization');

            match ($distribution->status) {
                DistributionStatus::RECEIVED => $this->record($distribution, 'events.distribution.received', $distribution->distributor, $distribution->eventAt ?? event_moment($distribution->reception_date)),
                DistributionStatus::IN_STORE => $this->record($distribution, 'events.distribution.in_store', $distribution->distributor, $distribution->eventAt),
                DistributionStatus::REJECTED => $this->record($distribution, 'events.distribution.rejected', $distribution->distributor, $distribution->eventAt),
                default => null,
            };
        }

        $this->scores->refresh($distribution->lot);
    }

    private function record(Distribution $distribution, string $message, $actor, ?CarbonInterface $occurredAt = null): void
    {
        $this->recorder->record(
            lot: $distribution->lot,
            type: EventType::DISTRIBUTION,
            description: __($message, [
                'distributor' => $distribution->distributor->displayName(),
                'destination' => $distribution->destination,
                'quantity' => format_quantity($distribution->quantity, $distribution->lot->unit),
            ]),
            actor: $actor,
            location: $distribution->destination,
            latitude: $distribution->latitude,
            longitude: $distribution->longitude,
            source: $distribution,
            occurredAt: $occurredAt,
            metadata: ['status' => $distribution->status->value],
        );
    }
}
