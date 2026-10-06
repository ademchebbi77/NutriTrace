<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        return view('products.index', [
            'products' => Product::visibleTo($request->user())
                ->with(['category', 'creator.organization'])
                ->withCount('lots')
                ->latest()
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('products.create', $this->formData(new Product));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $product = $this->products->create($request->user(), $request->safe()->except('image'), $request->file('image'));

        return redirect(area_route('products.show', $product))->with('success', __('products.created'));
    }

    public function show(Product $product): View
    {
        Gate::authorize('view', $product);

        return view('products.show', [
            'product' => $product->load(['category', 'creator.organization']),
            'lots' => $product->lots()->with(['currentHolder.organization', 'production'])->latest()->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return view('products.edit', $this->formData($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $this->products->update($product, $request->safe()->except('image'), $request->file('image'));

        return redirect(area_route('products.show', $product))->with('success', __('products.updated'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $this->products->delete($product);

        return redirect(area_route('products.index'))->with('success', __('products.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'statuses' => ProductStatus::cases(),
        ];
    }
}
