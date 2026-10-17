<?php

namespace App\Services\Ai;

use App\Models\Distribution;
use App\Models\ReceptionCheck;

/**
 * Advice before a distributor accepts a lot: the reception model of the AI service
 * recommends accepting, accepting with reservations or refusing, and the LLM explains
 * why. Cached per version of the lot's data.
 */
class ReceptionAdvisor
{
    public function __construct(
        private readonly DistributionAiContext $context,
        private readonly AiClient $client,
    ) {}

    public function current(Distribution $distribution): ?ReceptionCheck
    {
        return ReceptionCheck::where('distribution_id', $distribution->id)
            ->where('data_version', $this->context->version($this->context->forReception($distribution)))
            ->latest('id')
            ->first();
    }

    public function check(Distribution $distribution): ?ReceptionCheck
    {
        $payload = $this->context->forReception($distribution);
        $keys = ['distribution_id' => $distribution->id, 'data_version' => $this->context->version($payload)];

        if ($cached = ReceptionCheck::where($keys)->first()) {
            return $cached;
        }

        $result = $this->client->checkReception($payload);

        if (! isset($result['decision'])) {
            return null;
        }

        return ReceptionCheck::firstOrCreate($keys, [
            'decision' => $result['decision'],
            'confidence' => (float) ($result['confidence'] ?? 0),
            'reasons' => array_values($result['reasons'] ?? []),
            'explanation' => $result['explanation'] ?? null,
            'transport_label' => $result['transport_label'] ?? null,
            'model' => $result['model'] ?? null,
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->client->has('reception');
    }
}
