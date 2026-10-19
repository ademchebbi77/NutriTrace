<?php

namespace App\Http\Controllers;

use App\Enums\DataSource;
use App\Http\Requests\ImpactRequest;
use App\Models\EnvironmentalImpact;
use App\Models\ImpactCheck;
use App\Models\Lot;
use App\Services\Ai\ImpactChecker;
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

    public function edit(Request $request, Lot $lot, FootprintCalculator $calculator, ImpactChecker $checker): View
    {
        Gate::authorize('declareImpact', $lot);

        $lot->load('product.category');
        $impact = $lot->environmentalImpact ?? $calculator->refresh($lot);

        return view('impacts.edit', [
            'lot' => $lot,
            'impact' => $impact,
            'stages' => $calculator->stages($lot),
            'declarable' => EnvironmentalImpact::DECLARABLE,
            'sources' => DataSource::declarable(),
            // Local AI: check of the figures before saving, and advice to improve the grade.
            'aiAvailable' => $checker->isAvailable(),
            'pendingCheck' => ImpactCheck::where('user_id', $request->user()->id)->where('lot_id', $lot->id)->find(session('ai_check')),
            'aiCheck' => $checker->current($lot),
            'advice' => $checker->currentAdvice($lot),
            'canAdvise' => $checker->canAdvise(),
        ]);
    }

    public function update(ImpactRequest $request, Lot $lot, FootprintCalculator $calculator, LotScoreManager $scores, ImpactChecker $checker): RedirectResponse
    {
        // The AI checks the figures first; the holder confirms them when it finds a problem. Never blocking.
        $declared = $request->validated('declared');
        if ($declared !== [] && ! $request->boolean('ai_confirmed') && $checker->isAvailable()) {
            $check = $checker->check($lot, $request->user(), $declared, explain: false);

            if ($check?->is_anomaly) {
                return back()->withInput()->with('ai_check', $check->id);
            }
        }

        $calculator->declare($lot, $request->user(), $declared);

        // The transparency score and the lots made from this one depend on these figures.
        $scores->refresh($lot);

        return redirect(area_route('impacts.edit', $lot))->with('success', __('impacts.updated'));
    }
}
