<?php

namespace App\Http\Controllers;

use App\Http\Requests\LotUpdateRequest;
use App\Models\Lot;
use App\Services\Scoring\GreenwashingWarnings;
use App\Services\Scoring\TrustScoreCalculator;
use App\Services\Traceability\ChainVerifier;
use App\Services\Traceability\JourneyBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Lots are never created by hand: they come from a production or a transformation.
 */
class LotController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lot::class);

        return view('lots.index', [
            'lots' => Lot::visibleTo($request->user())
                ->with(['product', 'currentHolder.organization', 'environmentalImpact'])
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Lot sheet with its full journey, the state of its hash chain and its scores.
     */
    public function show(Lot $lot, JourneyBuilder $journeys, ChainVerifier $verifier, TrustScoreCalculator $trust, GreenwashingWarnings $warnings): View
    {
        Gate::authorize('view', $lot);

        $lot->load(['product.category', 'product.certifications', 'certifications', 'currentHolder.organization', 'environmentalImpact']);
        $journey = $journeys->build($lot);

        return view('lots.show', [
            'lot' => $lot,
            'journey' => $journey,
            'chain' => $verifier->verifyJourney($journey),
            'trust' => $trust->calculate($lot, $journey),
            'warnings' => $warnings->for($lot, $journey),
        ]);
    }

    public function edit(Lot $lot): View
    {
        Gate::authorize('update', $lot);

        return view('lots.edit', ['lot' => $lot->load('product')]);
    }

    public function update(LotUpdateRequest $request, Lot $lot): RedirectResponse
    {
        $lot->update($request->validated());

        return redirect(area_route('lots.show', $lot))->with('success', __('lots.updated'));
    }
}
