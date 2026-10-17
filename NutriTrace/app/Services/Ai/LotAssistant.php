<?php

namespace App\Services\Ai;

use App\Models\AiAnswer;
use App\Models\Lot;

/**
 * Questions about a lot answered by the local LLM from the lot's real data.
 * Answers are cached per question and per version of the data.
 */
class LotAssistant
{
    public function __construct(
        private readonly AiContext $context,
        private readonly AiClient $client,
    ) {}

    /**
     * Cached answer, or a new one from the AI service; null when neither is available.
     */
    public function ask(Lot $lot, string $question): ?AiAnswer
    {
        $context = $this->context->forLot($lot);
        $keys = [
            'lot_id' => $lot->id,
            'question_hash' => AiAnswer::hashQuestion($question),
            'data_version' => $this->context->version($context),
        ];

        if ($cached = AiAnswer::where($keys)->first()) {
            return $cached;
        }

        $reply = $this->client->chat($context, $question);

        if (blank($reply['answer'] ?? null)) {
            return null;
        }

        return AiAnswer::firstOrCreate($keys, [
            'question' => $question,
            'answer' => trim($reply['answer']),
            'model' => $reply['model'] ?? null,
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->client->canChat();
    }
}
