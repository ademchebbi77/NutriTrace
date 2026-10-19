<?php

namespace App\Services\Ai;

use App\Models\Production;
use Illuminate\Support\Facades\Log;

/**
 * Calls the local Python /production/forecast endpoint and returns a
 * structured result ready for the Blade view.
 *
 * Fails softly: if the Python service is unavailable, returns a status
 * of "service_unavailable" so the page degrades gracefully.
 */
class ProductionForecastService
{
    public function __construct(
        private readonly AiClient $client,
        private readonly ProductionForecastContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(Production $production): array
    {
        $payload = $this->context->forProduction($production);

        if ($payload === null) {
            return $this->noData();
        }

        if (! $this->client->available()) {
            return $this->unavailable();
        }

        $result = $this->client->productionForecast($payload['records'], $payload['unit']);

        if ($result === null) {
            Log::warning('Production forecast: Python service returned null', [
                'production_id' => $production->id,
            ]);

            return $this->unavailable();
        }

        return $result;
    }

    public function isAvailable(): bool
    {
        return $this->client->available();
    }

    /** @return array<string, mixed> */
    private function noData(): array
    {
        return [
            'status'           => 'no_data',
            'unit'             => null,
            'history'          => [],
            'statistics'       => null,
            'trend_per_year'   => null,
            'trend_direction'  => null,
            'baseline_estimate' => null,
            'prediction'       => null,
            'model_info'       => ['name' => null, 'trained' => false, 'reason' => null],
            'explanation'      => __('ai.production.no_data'),
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(): array
    {
        return [
            'status'           => 'service_unavailable',
            'unit'             => null,
            'history'          => [],
            'statistics'       => null,
            'trend_per_year'   => null,
            'trend_direction'  => null,
            'baseline_estimate' => null,
            'prediction'       => null,
            'model_info'       => ['name' => null, 'trained' => false, 'reason' => null],
            'explanation'      => __('ai.production.unavailable'),
        ];
    }
}
