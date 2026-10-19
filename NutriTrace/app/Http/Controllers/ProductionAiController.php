<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Services\Ai\ProductionForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Serves the production AI forecasting panel via a JSON endpoint.
 * The Blade view calls this asynchronously so the main production page
 * always loads even when the Python service is down.
 */
class ProductionAiController extends Controller
{
    public function __construct(
        private readonly ProductionForecastService $forecast,
    ) {}

    /**
     * GET /producteur/productions/{production}/forecast
     *
     * Returns the forecast JSON for the Blade panel.
     */
    public function show(Production $production): JsonResponse
    {
        Gate::authorize('view', $production);

        $result = $this->forecast->analyze($production);

        return response()->json($result);
    }
}
