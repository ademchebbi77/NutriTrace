<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransformationRequest;
use App\Models\Transformation;
use App\Services\Ai\TransformationChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Local AI on transformations: check of the form before saving (transformer) and
 * explanation of a saved transformation's review (admin).
 */
class TransformationAiController extends Controller
{
    /**
     * "Vérifier avec l'IA" button of the form: same validation as saving, nothing is saved.
     */
    public function check(TransformationRequest $request, TransformationChecker $checker): JsonResponse
    {
        $check = $checker->checkForm($request->user(), $request->validated(), explain: true);

        if (! $check) {
            return response()->json(['message' => __('ai.transformation.unavailable')], 503);
        }

        return response()->json($check->toResult());
    }

    public function explain(Transformation $transformation, TransformationChecker $checker): RedirectResponse
    {
        Gate::authorize('view', $transformation);

        $check = $checker->forAdmin($transformation, explain: true);

        return back()->with(
            $check?->explanation ? 'success' : 'error',
            $check?->explanation ? __('ai.transformation.explained') : __('ai.transformation.unavailable'),
        );
    }
}
