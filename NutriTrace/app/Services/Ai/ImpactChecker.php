<?php

namespace App\Services\Ai;

use App\Models\ImpactAdvice;
use App\Models\ImpactCheck;
use App\Models\Lot;
use App\Models\User;

/**
 * Environmental figures of a lot checked by the local AI before they are declared, and
 * advice to improve the lot's grade. Both cached per version of the data.
 */
class ImpactChecker
{
    public function __construct(
        private readonly ImpactAiContext $context,
        private readonly AiClient $client,
    ) {}

    /**
     * @param  array<string, array{value: float|string, source: string}>  $declared  Validated ImpactRequest data.
     */
    public function check(Lot $lot, User $user, array $declared, bool $explain): ?ImpactCheck
    {
        $payload = $this->context->forDeclaration($lot, $declared);
        $keys = ['lot_id' => $lot->id, 'data_version' => $this->context->version($payload)];
        $cached = ImpactCheck::where($keys)->first();

        if ($cached && (! $explain || $cached->explanation)) {
            return $cached;
        }

        $result = $this->client->checkImpact($payload, $explain);

        if (! isset($result['label'])) {
            return $cached;
        }

        return ImpactCheck::updateOrCreate($keys, [
            'user_id' => $user->id,
            'label' => $result['label'],
            'is_anomaly' => (bool) $result['is_anomaly'],
            'confidence' => (float) ($result['confidence'] ?? 0),
            'findings' => $result['findings'] ?? [],
            'reasons' => array_values($result['reasons'] ?? []),
            'explanation' => $result['explanation'] ?? $cached?->explanation,
            'model' => $result['model'] ?? null,
        ]);
    }

    /**
     * The last check of the figures currently declared for the lot.
     */
    public function current(Lot $lot): ?ImpactCheck
    {
        $declared = $lot->environmentalImpact?->declared ?? [];

        return $declared === [] ? null : ImpactCheck::where([
            'lot_id' => $lot->id,
            'data_version' => $this->context->version($this->context->forDeclaration($lot, $declared)),
        ])->first();
    }

    public function currentAdvice(Lot $lot): ?ImpactAdvice
    {
        return ImpactAdvice::where(['lot_id' => $lot->id, 'data_version' => $this->context->version($this->context->forAdvice($lot))])->first();
    }

    public function advise(Lot $lot): ?ImpactAdvice
    {
        $payload = $this->context->forAdvice($lot);
        $keys = ['lot_id' => $lot->id, 'data_version' => $this->context->version($payload)];

        if ($cached = ImpactAdvice::where($keys)->first()) {
            return $cached;
        }

        $result = $this->client->impactAdvice($payload);

        if (! isset($result['levers'])) {
            return null;
        }

        return ImpactAdvice::firstOrCreate($keys, [
            'grade' => $result['grade'] ?? null,
            'score' => $result['score'] ?? null,
            'target_grade' => $result['target'] ?? null,
            'levers' => array_values($result['levers']),
            'explanation' => $result['explanation'] ?? null,
            'model' => $result['model'] ?? null,
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->client->has('impact_check');
    }

    public function canAdvise(): bool
    {
        return $this->client->available();
    }
}
