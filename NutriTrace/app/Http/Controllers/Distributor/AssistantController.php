<?php

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssistantQuestionRequest;
use App\Services\Ai\DistributorAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * "Assistant IA" page of the distributor: questions about its own receptions,
 * stock, expiry dates and sales, answered by the local LLM.
 */
class AssistantController extends Controller
{
    public function show(DistributorAssistant $assistant): View
    {
        return view('distributor.assistant', [
            'available' => $assistant->isAvailable(),
            'training' => $assistant->isTraining(),
        ]);
    }

    public function ask(AssistantQuestionRequest $request, DistributorAssistant $assistant): JsonResponse
    {
        $answer = $assistant->ask($request->user(), $request->validated('question'));

        if (! $answer) {
            return response()->json([
                'message' => $assistant->isTraining() ? __('ai.assistant.training') : __('ai.assistant.unavailable'),
            ], 503);
        }

        return response()->json([
            'question' => $request->validated('question'),
            'answer' => $answer->answer,
            'cached' => ! $answer->wasRecentlyCreated,
        ]);
    }
}
