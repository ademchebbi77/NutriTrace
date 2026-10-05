<?php

namespace App\Http\Controllers;

use App\Enums\DataSource;
use App\Http\Requests\ImpactRequest;
use App\Models\EnvironmentalImpact;
use App\Models\Lot;
use App\Services\Scoring\FootprintCalculator;
use App\Services\Scoring\LotScoreManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Environmental data of the lots an actor holds: the calculated footprint,
 * and the figures the actor measured or provides itself.
 */
class ImpactController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lot::class);

        return view('impacts.index', [
            'lots' => Lot::heldBy($request->user())->with(['product', 'environmentalImpact'])->latest()->get(),
        ]);
    }

    public function edit(Lot $lot, FootprintCalculator $calculator): View
    {
        Gate::authorize('declareImpact', $lot);

        return view('impacts.edit', [
            'lot' => $lot->load('product'),
            'impact' => $lot->environmentalImpact ?? $calculator->refresh($lot),
            'stages' => $calculator->stages($lot),
            'declarable' => EnvironmentalImpact::DECLARABLE,
            'sources' => DataSource::declarable(),
        ]);
    }

    public function update(ImpactRequest $request, Lot $lot, FootprintCalculator $calculator, LotScoreManager $scores): RedirectResponse
    {
        $calculator->declare($lot, $request->user(), $request->validated('declared'));

        // The transparency score and the lots made from this one depend on these figures.
        $scores->refresh($lot);

        return redirect(area_route('impacts.edit', $lot))->with('success', __('impacts.updated'));
    }
}
