<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Http\Requests\RejectHandoverRequest;
use App\Models\Distribution;
use App\Models\TraceabilityEvent;
use App\Services\LotReceptionService;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DistributionController extends Controller
{
    public function __construct(private readonly LotReceptionService $receptions) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Distribution::class);

        return view('distributions.index', [
            'distributions' => Distribution::visibleTo($request->user())
                ->with(['lot.product', 'sender.organization', 'distributor.organization'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Distribution $distribution): View
    {
        Gate::authorize('view', $distribution);

        return view('distributions.show', [
            'distribution' => $distribution->load(['lot.product', 'sender.organization', 'distributor.organization', 'transport']),
            'sales' => $this->salesOf($distribution),
        ]);
    }

    public function receive(Request $request, Distribution $distribution): RedirectResponse
    {
        Gate::authorize('respond', $distribution);

        $data = $request->validate(['destination' => ['nullable', 'string', 'max:255']], [], ['destination' => __('distributions.attributes.destination')]);

        $this->receptions->receiveDistribution($distribution, $data['destination'] ?? null);

        return redirect()->route('distributeur.distributions.show', $distribution)
            ->with('success', __('transfers.received', ['lot' => $distribution->lot->lot_number]));
    }

    public function reject(RejectHandoverRequest $request, Distribution $distribution): RedirectResponse
    {
        Gate::authorize('respond', $distribution);

        $this->receptions->rejectDistribution($distribution, $request->validated('rejection_reason'));

        return back()->with('success', __('transfers.refused', ['lot' => $distribution->lot->lot_number]));
    }

    public function markInStore(Distribution $distribution): RedirectResponse
    {
        Gate::authorize('markInStore', $distribution);

        $this->receptions->markInStore($distribution);

        return back()->with('success', __('distributions.in_store', ['lot' => $distribution->lot->lot_number]));
    }

    /**
     * Lots on the shelves, with the form to record a sale and the latest sales.
     */
    public function sales(Request $request): View
    {
        Gate::authorize('viewAny', Distribution::class);

        $distributions = Distribution::where('distributor_id', $request->user()->id)
            ->with('lot.product')
            ->latest()
            ->get();

        return view('sales.index', [
            'onShelf' => $distributions->filter(fn (Distribution $distribution) => $request->user()->can('sell', $distribution))->values(),
            'sales' => TraceabilityEvent::where('event_type', EventType::SALE)
                ->where('actor_id', $request->user()->id)
                ->with('lot.product')
                ->latest('occurred_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function sell(Request $request, Distribution $distribution, SaleService $sales): RedirectResponse
    {
        Gate::authorize('sell', $distribution);

        $data = $request->validate(['quantity' => ['required', 'numeric', 'gt:0']], [], ['quantity' => __('distributions.attributes.quantity')]);

        $sales->record($distribution, (float) $data['quantity']);

        return back()->with('success', __('distributions.sales.recorded', ['lot' => $distribution->lot->lot_number]));
    }

    /**
     * @return Collection<int, TraceabilityEvent>
     */
    private function salesOf(Distribution $distribution)
    {
        return TraceabilityEvent::where('event_type', EventType::SALE)
            ->where('source_type', $distribution->getMorphClass())
            ->where('source_id', $distribution->id)
            ->latest('occurred_at')
            ->get();
    }
}
