<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\Unit;
use App\Http\Requests\TransformationRequest;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Transformation;
use App\Models\TransformationCheck;
use App\Services\Ai\TransformationChecker;
use App\Services\TransformationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Transformations are recorded once: they consume stock and start a new chain,
 * so they are not edited or deleted afterwards.
 */
class TransformationController extends Controller
{
    public function __construct(private readonly TransformationChecker $checker) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Transformation::class);

        $transformations = Transformation::visibleTo($request->user())
            ->with(['outputLot.product.category', 'inputs.lot.product.category', 'transformer.organization'])
            ->latest('transformation_date')
            ->get();

        return view('transformations.index', [
            'transformations' => $transformations,
            // The admin reviews every transformation with the AI's risk.
            'checks' => $request->user()->isAdmin() ? $this->checker->forList($transformations) : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Transformation::class);

        $user = $request->user();

        return view('transformations.create', [
            'lots' => Lot::heldBy($user)->with('product')->orderBy('production_date')->get()->filter->isAvailable()->values(),
            'products' => Product::where('created_by', $user->id)
                ->where('status', '!=', ProductStatus::ARCHIVED)
                ->orderBy('name')
                ->get(),
            'units' => Unit::cases(),
            'aiAvailable' => $this->checker->isAvailable(),
            // Problems found when the form was submitted: shown with a confirmation box.
            'pendingCheck' => TransformationCheck::where('user_id', $user->id)->find(session('ai_check')),
        ]);
    }

    public function store(TransformationRequest $request, TransformationService $transformations): RedirectResponse
    {
        // A transformation cannot be edited: the AI checks it first, and the transformer
        // confirms the values when it finds a problem. Never blocking, and skipped when the AI is off.
        if (! $request->boolean('ai_confirmed') && $this->checker->isAvailable()) {
            $check = $this->checker->checkForm($request->user(), $request->validated(), explain: false);

            if ($check?->is_anomaly && $check->label !== 'resources_missing') {
                return back()->withInput()->with('ai_check', $check->id);
            }
        }

        $transformation = $transformations->create($request->user(), $request->validated());
        $this->checker->attach($transformation);

        return redirect(area_route('transformations.show', $transformation))
            ->with('success', __('transformations.created', ['lot' => $transformation->outputLot->lot_number]));
    }

    public function show(Request $request, Transformation $transformation): View
    {
        Gate::authorize('view', $transformation);

        $admin = $request->user()->isAdmin();

        return view('transformations.show', [
            'transformation' => $transformation->load(['outputLot.product', 'inputs.lot.product', 'inputs.lot.production.producer.organization', 'transformer.organization']),
            'aiCheck' => $admin
                ? ($this->checker->currentForAdmin($transformation) ?? ($this->checker->isAvailable() ? $this->checker->forAdmin($transformation) : null))
                : $this->checker->forTransformer($transformation),
            'canExplain' => $admin && $this->checker->canExplain(),
        ]);
    }
}
