<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssistantQuestionRequest;
use App\Models\Lot;
use App\Services\Ai\LotAssistant;
use Illuminate\Http\JsonResponse;

/**
 * AI assistant of the public traceability page: anyone holding the QR code can ask.
 */
class AssistantController extends Controller
{
    public function ask(AssistantQuestionRequest $request, string $token, LotAssistant $assistant): JsonResponse
    {
        $lot = Lot::publiclyVisible()->where('public_token', $token)->firstOrFail();

        return self::reply($assistant, $lot, $request->validated('question'));
    }

    /**
     * JSON answer, shared with the back office assistant.
     */
    public static function reply(LotAssistant $assistant, Lot $lot, string $question): JsonResponse
    {
        $answer = $assistant->ask($lot, $question);

        if (! $answer) {
            return response()->json(['message' => __('ai.assistant.unavailable')], 503);
        }

        return response()->json([
            'question' => $question,
            'answer' => $answer->answer,
            'cached' => ! $answer->wasRecentlyCreated,
        ]);
    }
}
