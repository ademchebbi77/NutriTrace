<?php

namespace App\Services;

use App\Enums\DistributionStatus;
use App\Enums\LotStatus;
use App\Enums\TransferStatus;
use App\Enums\TransportStatus;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Models\Transport;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Receiver's side of a hand-over: confirming changes the lot's holder,
 * refusing sends the lot back to its sender.
 */
class LotReceptionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function acceptTransfer(LotTransfer $transfer, ?CarbonInterface $at = null): void
    {
        DB::transaction(function () use ($transfer, $at) {
            $at ??= now();

            $transfer->forceFill(['status' => TransferStatus::ACCEPTED, 'responded_at' => $at])->save();

            $this->deliver($transfer->transport, $at);
            $this->handOver($transfer->lot, $transfer->recipient, LotStatus::IN_TRANSFORMATION);

            $this->audit->log('lot.received', $transfer->lot, $transfer->lot->lot_number);
        });
    }

    public function rejectTransfer(LotTransfer $transfer, string $reason, ?CarbonInterface $at = null): void
    {
        DB::transaction(function () use ($transfer, $reason, $at) {
            $transfer->forceFill([
                'status' => TransferStatus::REJECTED,
                'rejection_reason' => $reason,
                'responded_at' => $at ?? now(),
            ])->save();

            $this->cancel($transfer->transport);
            $this->returnToSender($transfer->lot, $transfer->sender);

            $this->audit->log('lot.refused', $transfer->lot, $transfer->lot->lot_number, ['reason' => $reason]);
        });
    }

    /**
     * @param  ?string  $destination  Store or region, when the distributor wants to refine it.
     */
    public function receiveDistribution(Distribution $distribution, ?string $destination = null, ?CarbonInterface $at = null): void
    {
        DB::transaction(function () use ($distribution, $destination, $at) {
            $at ??= now();

            // Deliver the transport first so "arrival" precedes "reception" in the journal.
            $this->deliver($distribution->transport, $at);

            $distribution->eventAt = $at;
            $distribution->fill(array_filter(['destination' => $destination]));
            $distribution->forceFill([
                'status' => DistributionStatus::RECEIVED,
                'reception_date' => $at->toDateString(),
            ])->save();

            $this->handOver($distribution->lot, $distribution->distributor, LotStatus::DISTRIBUTED);

            $this->audit->log('lot.received', $distribution->lot, $distribution->lot->lot_number);
        });
    }

    public function rejectDistribution(Distribution $distribution, string $reason, ?CarbonInterface $at = null): void
    {
        DB::transaction(function () use ($distribution, $reason, $at) {
            $this->cancel($distribution->transport);

            $distribution->eventAt = $at;
            $distribution->forceFill([
                'status' => DistributionStatus::REJECTED,
                'rejection_reason' => $reason,
            ])->save();

            $this->returnToSender($distribution->lot, $distribution->sender);

            $this->audit->log('lot.refused', $distribution->lot, $distribution->lot->lot_number, ['reason' => $reason]);
        });
    }

    public function markInStore(Distribution $distribution, ?CarbonInterface $at = null): void
    {
        DB::transaction(function () use ($distribution, $at) {
            $distribution->eventAt = $at;
            $distribution->forceFill(['status' => DistributionStatus::IN_STORE])->save();

            $distribution->lot->refresh()->forceFill(['status' => LotStatus::IN_STORE])->save();
        });
    }

    private function deliver(?Transport $transport, CarbonInterface $at): void
    {
        $transport?->forceFill(['status' => TransportStatus::DELIVERED, 'arrival_date' => $at])->save();
    }

    private function cancel(?Transport $transport): void
    {
        $transport?->forceFill(['status' => TransportStatus::CANCELLED])->save();
    }

    /**
     * Lots are re-read before being changed: the copy cached on a hand-over
     * may date from before the lot went in transit.
     */
    private function handOver(Lot $lot, User $recipient, LotStatus $status): void
    {
        $lot->refresh()->forceFill(['current_holder_id' => $recipient->id, 'status' => $status])->save();
    }

    private function returnToSender(Lot $lot, User $sender): void
    {
        $lot->refresh()->forceFill(['status' => $lot->restingStatusFor($sender)])->save();
    }
}
