<?php

namespace App\Http\Controllers\Consumer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consumer account area: what the user looked at, bookmarked, reviewed and reported.
 */
class AreaController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();

        return view('consumer.dashboard', [
            'user' => $user,
            'counts' => [
                'history' => $user->viewedLots()->count(),
                'favorites' => $user->favoriteProducts()->count(),
                'reviews' => $user->reviews()->count(),
                'reports' => $user->reports()->count(),
            ],
            'recent' => $user->viewedLots()->with(['product', 'environmentalImpact'])->limit(5)->get(),
        ]);
    }

    public function history(Request $request): View
    {
        return view('consumer.history', [
            'lots' => $request->user()->viewedLots()->with(['product', 'environmentalImpact'])->get(),
        ]);
    }

    public function favorites(Request $request): View
    {
        return view('consumer.favorites', [
            'products' => $request->user()->favoriteProducts()->with('category')->orderBy('name')->get(),
        ]);
    }

    public function reviews(Request $request): View
    {
        return view('consumer.reviews', [
            'reviews' => $request->user()->reviews()->with(['product', 'lot'])->latest()->get(),
        ]);
    }

    public function reports(Request $request): View
    {
        return view('consumer.reports', [
            'reports' => $request->user()->reports()->with('reportable')->latest()->get(),
        ]);
    }
}
