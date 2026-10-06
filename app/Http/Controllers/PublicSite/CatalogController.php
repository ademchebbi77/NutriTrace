<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /**
     * Product grid with search and filters (category, origin).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'origin' => ['nullable', 'string', 'max:100'],
        ]);

        $products = Product::published()
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(
                fn (Builder $sub) => $sub
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('barcode', $q)
            ))
            ->when($filters['category'] ?? null, fn (Builder $query, $category) => $query->where('category_id', $category))
            ->when($filters['origin'] ?? null, fn (Builder $query, string $origin) => $query->where('origin', 'like', "%{$origin}%"))
            ->with(['category'])
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        return view('public.catalog', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->get(),
            'origins' => Product::published()->whereNotNull('origin')->distinct()->orderBy('origin')->pluck('origin'),
        ]);
    }

    /**
     * Product detail page.
     */
    public function show(Product $product): View
    {
        abort_unless($product->isPubliclyVisible(), 404);

        $product->load(['category', 'creator.organization', 'productions']);

        $related = Product::published()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->with(['category'])
            ->limit(4)
            ->get();

        return view('public.product', [
            'product' => $product,
            'related' => $related,
        ]);
    }

    /**
     * Search box: a barcode opens the product, anything else searches the catalog.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return redirect()->route('catalog.index');
        }

        $product = preg_match('/^\d{8}(\d{5})?$/', $q)
            ? Product::publiclyVisible()->where('barcode', $q)->first()
            : null;

        return $product
            ? redirect()->route('catalog.show', $product)
            : redirect()->route('catalog.index', ['q' => $q]);
    }
}
