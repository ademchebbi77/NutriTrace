<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Services\LotInsightsBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompareController extends Controller
{
    public const MAX_LOTS = 3;

    /**
     * Side-by-side comparison of two or three lots, chosen by lot number.
     */
    public function __invoke(Request $request, LotInsightsBuilder $insights): View
    {
        $numbers = collect((array) $request->query('lots', []))
            ->map(fn ($number) => strtoupper(trim((string) $number)))
            ->filter()
            ->unique()
            ->take(self::MAX_LOTS)
            ->values();

        $lots = $numbers->isEmpty()
            ? collect()
            : Lot::publiclyVisible()->whereIn('lot_number', $numbers)->get()
                ->sortBy(fn (Lot $lot) => $numbers->search($lot->lot_number))
                ->values();

        return view('public.compare', [
            'numbers' => $numbers,
            'notFound' => $numbers->diff($lots->pluck('lot_number'))->values(),
            'compared' => $lots->map(fn (Lot $lot) => $insights->for($lot)),
            // Suggestions for the selector: recent lots that already have a footprint.
            'suggestions' => Lot::publiclyVisible()
                ->whereHas('environmentalImpact', fn ($impact) => $impact->whereNotNull('score'))
                ->with('product')
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }
}
