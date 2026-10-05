<?php

namespace App\Http\Controllers;

use App\Enums\TransportType;
use App\Http\Requests\TransportUpdateRequest;
use App\Models\Transport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A transport is created when a lot is sent (see TransferController).
 * Here actors follow their shipments and correct mode or distance while on the road.
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

    public function show(Transport $transport): View
    {
        Gate::authorize('view', $transport);

        return view('transports.show', [
            'transport' => $transport->load(['lot.product', 'shipper.organization', 'recipient.organization']),
            'transportTypes' => TransportType::cases(),
            'co2' => $transport->massKg() !== null && $transport->distance_km !== null
                ? $transport->distance_km * ($transport->massKg() / 1000) * config('footprint.emission_factors.transport.'.$transport->transport_type->value)
                : null,
        ]);
    }

    public function update(TransportUpdateRequest $request, Transport $transport): RedirectResponse
    {
        $transport->update($request->validated());

        return back()->with('success', __('transfers.transport_updated'));
    }
}
