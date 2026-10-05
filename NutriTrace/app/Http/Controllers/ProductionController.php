<?php

namespace App\Http\Controllers;

use App\Enums\ProductionMethod;
use App\Enums\ProductStatus;
use App\Enums\Unit;
use App\Http\Requests\ProductionRequest;
use App\Models\Product;
use App\Models\Production;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(private readonly ProductionService $productions) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Production::class);

        return view('productions.index', [
            'productions' => Production::visibleTo($request->user())
                ->with(['product', 'lot', 'producer.organization'])
                ->latest('production_date')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Production::class);

        // Pre-fill the location with the producer's organization.
        $organization = $request->user()->organization;

        $production = new Production([
            'location_address' => $organization?->address,
            'location_city' => $organization?->city,
            'latitude' => $organization?->latitude,
            'longitude' => $organization?->longitude,
            'production_date' => today(),
        ]);

        return view('productions.create', $this->formData($request, $production));
    }

    public function store(ProductionRequest $request): RedirectResponse
    {
        Gate::authorize('create', Production::class);

        $production = $this->productions->create($request->user(), $request->validated());

        return redirect(area_route('productions.show', $production))
            ->with('success', __('productions.created', ['lot' => $production->lot->lot_number]));
    }

    public function show(Production $production): View
    {
        Gate::authorize('view', $production);

        return view('productions.show', [
            'production' => $production->load(['product.category', 'lot.currentHolder.organization', 'producer.organization']),
        ]);
    }

    public function edit(Request $request, Production $production): View
    {
        Gate::authorize('update', $production);

        return view('productions.edit', $this->formData($request, $production));
    }

    public function update(ProductionRequest $request, Production $production): RedirectResponse
    {
        Gate::authorize('update', $production);

        $this->productions->update($production, $request->validated());

        return redirect(area_route('productions.show', $production))->with('success', __('productions.updated'));
    }

    public function destroy(Production $production): RedirectResponse
    {
        Gate::authorize('delete', $production);

        $this->productions->delete($production);

        return redirect(area_route('productions.index'))->with('success', __('productions.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, Production $production): array
    {
        return [
            'production' => $production,
            'products' => Product::where('created_by', $request->user()->id)
                ->where('status', '!=', ProductStatus::ARCHIVED)
                ->orderBy('name')
                ->get(),
            'units' => Unit::cases(),
            'methods' => ProductionMethod::cases(),
        ];
    }
}
