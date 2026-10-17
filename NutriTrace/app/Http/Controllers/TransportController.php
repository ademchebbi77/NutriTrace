<?php

namespace App\Http\Controllers;

use App\Enums\TransportType;
use App\Http\Requests\TransportStoreRequest;
use App\Http\Requests\TransportUpdateRequest;
use App\Models\Lot;
use App\Models\Transport;
use App\Models\User;
use App\Services\Ai\LotAssistant;
use App\Services\Ai\TransportAnalyzer;
use App\Services\LotDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A transport carries a whole lot to a transformer or a distributor.
 * Opening one sends the lot (same flow as TransferController); while the lot is
 * on the road the shipper can correct mode and distance, or cancel the shipment.
 * A transport is never erased: its events stay in the traceability journal.
 */
class TransportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Transport::class);

        return view('transports.index', [
            'transports' => Transport::visibleTo($request->user())
                ->with(['lot.product', 'shipper.organization', 'recipient.organization'])
                ->latest('departure_date')
                ->get(),
        ]);
    }

    public function create(Request $request, LotDispatcher $dispatcher): View
    {
        Gate::authorize('create', Transport::class);

        return view('transports.create', [
            'lots' => Lot::heldBy($request->user())->with('product')->orderBy('lot_number')->get()->filter->isAvailable()->values(),
            'recipients' => $dispatcher->recipientsFor($request->user()),
            'transportTypes' => TransportType::cases(),
            'selectedLot' => $request->integer('lot') ?: null,
        ]);
    }

    public function store(TransportStoreRequest $request, LotDispatcher $dispatcher): RedirectResponse
    {
        $lot = $request->lot();
        $recipient = User::with('organization')->findOrFail($request->validated('recipient_id'));

        $handover = $dispatcher->send($lot, $request->user(), $recipient, $request->validated());

        return redirect(area_route('transports.show', $handover->transport_id))
            ->with('success', __('transfers.sent', ['lot' => $lot->lot_number, 'recipient' => $recipient->displayName()]));
    }

    public function show(Transport $transport, TransportAnalyzer $analyzer, LotAssistant $assistant): View
    {
        Gate::authorize('view', $transport);

        return view('transports.show', [
            'transport' => $transport->load(['lot.product', 'shipper.organization', 'recipient.organization']),
            'co2' => $transport->co2Kg(),
            'aiAvailable' => $analyzer->isAvailable(),
            'chatAvailable' => $assistant->isAvailable(),
            'analysis' => $analyzer->current($transport),
            'staleAnalysis' => $transport->analyses()->exists(),
        ]);
    }

    public function edit(Transport $transport): View
    {
        Gate::authorize('update', $transport);

        return view('transports.edit', [
            'transport' => $transport->load('lot.product'),
            'transportTypes' => TransportType::cases(),
        ]);
    }

    public function update(TransportUpdateRequest $request, Transport $transport): RedirectResponse
    {
        $transport->update($request->validated());

        return redirect(area_route('transports.show', $transport))->with('success', __('transfers.transport_updated'));
    }

    /**
     * Cancels the shipment instead of deleting the row (see LotDispatcher::cancel).
     */
    public function destroy(Transport $transport, LotDispatcher $dispatcher): RedirectResponse
    {
        Gate::authorize('delete', $transport);

        $dispatcher->cancel($transport);

        return redirect(area_route('transports.index'))
            ->with('success', __('transfers.transport_cancelled', ['lot' => $transport->lot->lot_number]));
    }
}
