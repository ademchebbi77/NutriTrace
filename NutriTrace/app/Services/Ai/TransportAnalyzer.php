<?php

namespace App\Services\Ai;

use App\Models\Transport;
use App\Models\TransportAnalysis;

/**
 * Anomaly check of a transport: the scikit-learn model of the AI service flags
 * it and the LLM explains why. Cached per version of the transport's data.
 */
class TransportAnalyzer
{
    public function __construct(
        private readonly AiContext $context,
        private readonly AiClient $client,
    ) {}

    /**
     * Analysis of the transport as it is now, if one was already made.
     */
    public function current(Transport $transport): ?TransportAnalysis
    {
        return $transport->analyses()
            ->where('data_version', $this->context->version($this->context->forTransport($transport)))
            ->first();
    }

    public function analyze(Transport $transport): ?TransportAnalysis
    {
        if ($cached = $this->current($transport)) {
            return $cached;
        }

        $payload = $this->context->forTransport($transport);
        $result = $this->client->analyzeTransport($payload);

        if (! isset($result['label'], $result['is_anomaly'])) {
            return null;
        }

        return TransportAnalysis::firstOrCreate(
            ['transport_id' => $transport->id, 'data_version' => $this->context->version($payload)],
            [
                'is_anomaly' => (bool) $result['is_anomaly'],
                'label' => $result['label'],
                'confidence' => (float) ($result['confidence'] ?? 0),
                'reasons' => array_values($result['reasons'] ?? []),
                'explanation' => $result['explanation'] ?? null,
                'model' => $result['model'] ?? null,
            ],
        );
    }

    public function isAvailable(): bool
    {
        return $this->client->available();
    }
}
