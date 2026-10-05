<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\CertificationType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Lot;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /**
     * Product grid with search and filters (category, certification, grade, origin).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'certification' => ['nullable', 'string', 'max:40'],
            'grade' => ['nullable', 'in:A,B,C,D,E'],
            'origin' => ['nullable', 'string', 'max:100'],
        ]);

        $certification = CertificationType::tryFrom($filters['certification'] ?? '');

        $products = Product::published()
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(
                fn (Builder $sub) => $sub
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('barcode', $q)
                    ->orWhereHas('lots', fn (Builder $lot) => $lot->where('lot_number', $q))
            ))
            ->when($filters['category'] ?? null, fn (Builder $query, $category) => $query->where('category_id', $category))
            ->when($filters['origin'] ?? null, fn (Builder $query, string $origin) => $query->where('origin', 'like', "%{$origin}%"))
            ->when($filters['grade'] ?? null, fn (Builder $query, string $grade) => $query->whereHas(
                'lots.environmentalImpact', fn (Builder $impact) => $impact->where('grade', $grade)
            ))
            // Only certifications consumers can rely on: verified and not expired.
            ->when($certification, fn (Builder $query) => $query->where(
                fn (Builder $sub) => $sub
                    ->whereHas('certifications', fn (Builder $c) => $c->valid()->where('type', $certification))
                    ->orWhereHas('lots.certifications', fn (Builder $c) => $c->valid()->where('type', $certification))
            ))
            ->with(['category', 'certifications', 'lots.environmentalImpact', 'lots.certifications'])
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        return view('public.catalog', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->get(),
            'certificationTypes' => CertificationType::cases(),
            'origins' => Product::published()->whereNotNull('origin')->distinct()->orderBy('origin')->pluck('origin'),
        ]);
    }

    /**
     * Product page with its lots and reviews.
     */
    public function show(Request $request, Product $product): View
    {
        abort_unless($product->isPubliclyVisible(), 404);

        $product->load(['category', 'certifications', 'creator.organization', 'reviews.user', 'lots.environmentalImpact', 'lots.certifications']);

        return view('public.product', [
            'product' => $product,
            'lots' => $product->lots->sortByDesc('production_date')->values(),
            'averageRating' => round((float) $product->reviews->avg('rating'), 1),
            'isFavorite' => $request->user()?->favoriteProducts()->whereKey($product->id)->exists() ?? false,
            'related' => Product::published()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->with(['category', 'certifications', 'lots.environmentalImpact', 'lots.certifications'])
                ->limit(4)
                ->get(),
        ]);
    }

    /**
     * Search box: a lot number or a QR token opens the lot, a barcode opens the product,
     * anything else searches the catalog.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return redirect()->route('catalog.index');
        }

        $lot = Lot::publiclyVisible()
            ->where(fn (Builder $query) => $query->where('lot_number', strtoupper($q))->orWhere('public_token', strtolower($q)))
            ->first();

        if ($lot) {
            return redirect()->route('trace.show', $lot->public_token);
        }

        $product = preg_match('/^\d{8}(\d{5})?$/', $q) ? Product::publiclyVisible()->where('barcode', $q)->first() : null;

        return $product
            ? redirect()->route('catalog.show', $product)
            : redirect()->route('catalog.index', ['q' => $q]);
    }
}
