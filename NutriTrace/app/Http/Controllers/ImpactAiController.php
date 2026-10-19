<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImpactRequest;
use App\Models\Lot;
use App\Services\Ai\ImpactChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Local AI on a lot's environmental data: check of the figures before declaring them,
 * and advice to improve the lot's grade.
 */
class ImpactAiController extends Controller
{
    /**
     * "Vérifier avec l'IA" button of the form: same validation as saving, nothing is saved.
     */
    public function check(ImpactRequest $request, Lot $lot, ImpactChecker $checker): JsonResponse
    {
        $declared = $request->validated('declared');

        if ($declared === []) {
            return response()->json(['message' => __('ai.impact.nothing_declared')], 422);
        }

        $check = $checker->check($lot, $request->user(), $declared, explain: true);

        return $check
            ? response()->json($check->toResult())
            : response()->json(['message' => __('ai.impact.unavailable')], 503);
    }

    public function advise(Lot $lot, ImpactChecker $checker): RedirectResponse
    {
        Gate::authorize('declareImpact', $lot);

        $advice = $checker->advise($lot);

        return back()->with($advice ? 'success' : 'error', $advice ? __('ai.advice.done') : __('ai.impact.unavailable'));
    }
}
