<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiClient;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "IA locale" admin page: state of the local AI service, scores of its models, and
 * training runs (export of the data, then training by the Python service).
 */
class AiController extends Controller
{
    public const MODULES = ['transport', 'distribution'];

    public function __construct(private readonly AiClient $client) {}

    public function index(): View
    {
        $this->client->forgetHealth();
        [$up, $models] = $this->client->admin('GET', '/models');

        return view('admin.ai.index', [
            'health' => $this->client->health(),
            'models' => $up ? $models : null,
            'modules' => self::MODULES,
            // Labels used by the progress script of the page.
            'scriptTexts' => collect(['states', 'stages', 'modules', 'step', 'eta', 'loss'])
                ->mapWithKeys(fn (string $key) => [$key => __('ai.admin.'.$key)])
                ->all(),
        ]);
    }

    /**
     * Polled by the page every few seconds during a run.
     */
    public function status(): JsonResponse
    {
        [$up, $status] = $this->client->admin('GET', '/train/status');

        return response()->json($up ? $status : ['state' => 'unreachable', 'message' => __('ai.admin.service_down')]);
    }

    public function train(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', Rule::in(self::MODULES)],
            'epochs' => ['nullable', 'numeric', 'min:0.5', 'max:3'],
            'resume' => ['nullable', 'boolean'],
        ]);
        $resume = (bool) ($data['resume'] ?? false);

        // Fresh data first: the dataset is generated from this export. A resumed run keeps its dataset.
        if (! $resume) {
            set_time_limit(300);
            Artisan::call('ai:export');
        }

        [$ok, $body] = $this->client->admin('POST', '/train', [
            'module' => $data['module'],
            'epochs' => (float) ($data['epochs'] ?? 1),
            'resume' => $resume,
        ]);

        if (! $ok) {
            return back()->with('error', __('ai.admin.train_refused', ['reason' => $body['detail'] ?? '?']));
        }

        $audit->log('ai.training_started', null, __('ai.admin.modules.'.$data['module']));

        return back()->with('success', __('ai.admin.train_started', ['module' => __('ai.admin.modules.'.$data['module'])]));
    }

    public function cancel(AuditLogger $audit): RedirectResponse
    {
        [$ok, $body] = $this->client->admin('POST', '/train/cancel');

        if ($ok) {
            $audit->log('ai.training_cancelled');
        }

        return back()->with($ok ? 'success' : 'error', $ok ? __('ai.admin.cancelled') : ($body['detail'] ?? __('ai.admin.service_down')));
    }
}
