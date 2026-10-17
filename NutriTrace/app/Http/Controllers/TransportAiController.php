<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PublicSite\AssistantController;
use App\Http\Requests\AssistantQuestionRequest;
use App\Models\Transport;
use App\Services\Ai\LotAssistant;
use App\Services\Ai\TransportAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Local AI on the transport page: questions about the transported lot and
 * anomaly check of the transport. Anyone who can see the transport can use it.
 */
class TransportAiController extends Controller
{
    public function ask(AssistantQuestionRequest $request, Transport $transport, LotAssistant $assistant): JsonResponse
    {
        Gate::authorize('view', $transport);

        return AssistantController::reply($assistant, $transport->lot, $request->validated('question'));
    }

    public function analyze(Transport $transport, TransportAnalyzer $analyzer): RedirectResponse
    {
        Gate::authorize('view', $transport);

        $analysis = $analyzer->analyze($transport);

        return back()->with(
            $analysis ? 'success' : 'error',
            $analysis ? __('ai.analysis.done') : __('ai.analysis.unavailable'),
        );
    }
}
