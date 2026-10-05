<?php

namespace App\Http\Controllers\Producer;

use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('organization');

        $lots = Lot::visibleTo($user)->get(['id', 'status', 'current_holder_id']);

        // Productions of the last 12 months, grouped in PHP to stay database-agnostic.
        $months = collect(range(11, 0))->map(fn (int $ago) => today()->startOfMonth()->subMonths($ago));

        $perMonth = Production::where('producer_id', $user->id)
            ->where('production_date', '>=', $months->first())
            ->pluck('production_date')
            ->countBy(fn (Carbon $date) => $date->format('Y-m'));

        return view('producer.dashboard', [
            'user' => $user,
            'stats' => [
                'products' => Product::where('created_by', $user->id)->count(),
                'productions' => Production::where('producer_id', $user->id)->count(),
                'lots_held' => $lots->where('current_holder_id', $user->id)->count(),
                'lots_transferred' => $lots->where('current_holder_id', '!=', $user->id)->count(),
            ],
            'latestProductions' => Production::where('producer_id', $user->id)
                ->with(['product', 'lot'])
                ->latest('production_date')
                ->limit(5)
                ->get(),
            'charts' => [
                'perMonth' => [
                    'labels' => $months->map(fn (Carbon $month) => $month->translatedFormat('M Y'))->all(),
                    'values' => $months->map(fn (Carbon $month) => $perMonth[$month->format('Y-m')] ?? 0)->all(),
                ],
                'byStatus' => [
                    'labels' => array_map(fn (LotStatus $status) => $status->label(), LotStatus::cases()),
                    'values' => array_map(fn (LotStatus $status) => $lots->where('status', $status)->count(), LotStatus::cases()),
                ],
            ],
        ]);
    }
}
