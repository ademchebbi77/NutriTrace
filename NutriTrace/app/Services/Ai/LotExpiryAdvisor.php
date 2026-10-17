<?php

namespace App\Services\Ai;

use App\Models\Lot;
use App\Models\LotExpiryCheck;
use Illuminate\Support\Collection;

/**
 * Date limits of lots judged against the shelf life the AI expects for their product
 * (gradient-boosting regressor of the AI service), for the admin overview of the lots.
 */
class LotExpiryAdvisor
{
    public function __construct(
        private readonly TransformationAiContext $context,
        private readonly AiClient $client,
    ) {}

    /**
     * @param  Collection<int, Lot>  $lots
     * @return Collection<int, LotExpiryCheck> keyed by lot id
     */
    public function forList(Collection $lots): Collection
    {
        $payloads = $lots->mapWithKeys(fn (Lot $lot) => [$lot->id => $this->context->forLotExpiry($lot)]);
        $versions = $payloads->map(fn (array $payload) => $this->context->version($payload));

        $checks = LotExpiryCheck::whereIn('lot_id', $lots->modelKeys())
            ->whereIn('data_version', $versions->values())
            ->get()
            ->filter(fn (LotExpiryCheck $check) => $versions[$check->lot_id] === $check->data_version)
            ->keyBy('lot_id');

        $missing = $payloads->keys()->diff($checks->keys());

        if ($missing->isNotEmpty() && $this->client->has('shelf_life')) {
            foreach ($this->client->lotsExpiry($payloads->only($missing->all())->values()->all()) ?? [] as $result) {
                if (isset($result['id'], $result['label'])) {
                    $checks[$result['id']] = LotExpiryCheck::updateOrCreate(
                        ['lot_id' => $result['id'], 'data_version' => $versions[$result['id']]],
                        [
                            'label' => $result['label'],
                            'predicted_days' => (int) $result['predicted_days'],
                            'low_days' => (int) $result['low_days'],
                            'high_days' => (int) $result['high_days'],
                            'actual_days' => $result['actual_days'],
                            'suggested_date' => $result['suggested_date'],
                            'reasons' => array_values($result['reasons'] ?? []),
                            'model' => 'gradient-boosting',
                        ],
                    );
                }
            }
        }

        return $checks;
    }
}
