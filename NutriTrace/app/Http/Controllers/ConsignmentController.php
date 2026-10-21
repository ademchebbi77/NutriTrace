<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Enums\TransportStatus;
use App\Models\Transport;
use App\Services\Traceability\TraceabilityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Electronic consignment note ("lettre de voiture") of a transport. The printed note carries
 * a QR code to a public page: anyone (a consumer) can check the transport there, and the
 * recipient, logged in, signs the hand-over with the quantity actually received.
 */
class ConsignmentController extends Controller
{
    /** Printable note (print or save as PDF from the browser). */
    public function note(Transport $transport): View
    {
        Gate::authorize('view', $transport);

        return view('transports.consignment', [
            'transport' => $transport->load(['lot.product', 'shipper.organization', 'recipient.organization']),
            'qr' => $this->qr($transport, 180),
        ]);
    }

    /** Public page reached by the QR code. */
    public function show(Request $request, string $token): View
    {
        $transport = Transport::where('consignment_token', $token)
            ->with(['lot.product', 'shipper.organization', 'recipient.organization', 'handedOverBy'])
            ->firstOrFail();

        return view('public.consignment', [
            'transport' => $transport,
            'qr' => $this->qr($transport, 140),
            'canSign' => $request->user()?->id === $transport->recipient_id && $this->signable($transport),
        ]);
    }

    /** The recipient confirms the hand-over: quantity received, optional comment. */
    public function sign(Request $request, string $token, TraceabilityRecorder $recorder): RedirectResponse
    {
        $transport = Transport::where('consignment_token', $token)->with('lot')->firstOrFail();

        abort_unless($request->user()->id === $transport->recipient_id, 403);
        abort_unless($this->signable($transport), 409, __('transfers.consignment.not_signable'));

        $data = $request->validate([
            'handover_quantity' => ['required', 'numeric', 'min:0', 'max:'.($transport->quantity_transported * 2)],
            'handover_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $transport->forceFill([
            'handover_quantity' => $data['handover_quantity'],
            'handover_comment' => $data['handover_comment'] ?? null,
            'handed_over_at' => now(),
            'handed_over_by' => $request->user()->id,
        ])->save();

        $gap = $transport->handoverGap();
        $unit = $transport->unit ?? $transport->lot->unit;

        // The signature becomes part of the tamper-proof journal of the lot.
        $recorder->record(
            lot: $transport->lot,
            type: EventType::TRANSPORT,
            description: __($gap == 0 ? 'transfers.consignment.event_signed' : 'transfers.consignment.event_gap', [
                'number' => $transport->consignment_number,
                'recipient' => $request->user()->displayName(),
                'received' => format_quantity($transport->handover_quantity, $unit),
                'sent' => format_quantity($transport->quantity_transported, $unit),
            ]),
            actor: $request->user(),
            location: $transport->destination_label,
            source: $transport,
            metadata: ['consignment' => $transport->consignment_number, 'received' => $transport->handover_quantity, 'gap' => $gap],
        );

        return back()->with($gap == 0 ? 'success' : 'warning', __($gap == 0
            ? 'transfers.consignment.signed'
            : 'transfers.consignment.signed_with_gap', ['gap' => format_quantity(abs($gap), $unit)]));
    }

    private function signable(Transport $transport): bool
    {
        return ! $transport->isHandedOver() && $transport->status !== TransportStatus::CANCELLED;
    }

    private function qr(Transport $transport, int $size): string
    {
        return (string) QrCode::format('svg')->size($size)->margin(1)->generate($transport->consignmentUrl());
    }
}
