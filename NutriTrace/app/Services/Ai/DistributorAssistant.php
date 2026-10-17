<?php

namespace App\Services\Ai;

use App\Models\AiAnswer;
use App\Models\DistributorAiAnswer;
use App\Models\User;

/**
 * Questions of a distributor about its own receptions, stock and sales, answered by
 * the local LLM (Distribution - Certification adapter). Cached per question and per
 * version of the data, which includes the date: answers are recomputed every day.
 */
class DistributorAssistant
{
    public function __construct(
        private readonly DistributionAiContext $context,
        private readonly AiClient $client,
    ) {}

    public function ask(User $distributor, string $question): ?DistributorAiAnswer
    {
        $context = $this->context->forDistributor($distributor);
        $keys = [
            'user_id' => $distributor->id,
            'question_hash' => AiAnswer::hashQuestion($question),
            'data_version' => $this->context->version($context),
        ];

        if ($cached = DistributorAiAnswer::where($keys)->first()) {
            return $cached;
        }

        $reply = $this->client->chatDistributor($context, $question);

        if (blank($reply['answer'] ?? null)) {
            return null;
        }

        return DistributorAiAnswer::firstOrCreate($keys, [
            'question' => $question,
            'answer' => trim($reply['answer']),
            'model' => $reply['model'] ?? null,
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->client->canChat();
    }

    public function isTraining(): bool
    {
        return $this->client->isTraining();
    }
}
