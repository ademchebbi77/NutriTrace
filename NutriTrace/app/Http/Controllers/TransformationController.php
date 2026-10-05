<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Enums\Unit;
use App\Http\Requests\TransformationRequest;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Transformation;
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
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Transformation::class);

        return view('transformations.index', [
            'transformations' => Transformation::visibleTo($request->user())
                ->with(['outputLot.product', 'inputs.lot.product', 'transformer.organization'])
                ->latest('transformation_date')
                ->get(),
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
        ]);
    }

    public function store(TransformationRequest $request, TransformationService $transformations): RedirectResponse
    {
        $transformation = $transformations->create($request->user(), $request->validated());

        return redirect(area_route('transformations.show', $transformation))
            ->with('success', __('transformations.created', ['lot' => $transformation->outputLot->lot_number]));
    }

    public function show(Transformation $transformation): View
    {
        Gate::authorize('view', $transformation);

        return view('transformations.show', [
            'transformation' => $transformation->load(['outputLot.product', 'inputs.lot.product', 'inputs.lot.production.producer.organization', 'transformer.organization']),
        ]);
    }
}
