<?php

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Enums\UserRole;
use App\Models\Lot;
use App\Models\TraceabilityEvent;
use App\Models\Transformation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard of the professional roles: their lots, their transformations
 * (transformers only) and their latest activity.
 */
class RoleDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('organization');
        $lots = Lot::heldBy($user)->get(['id', 'status']);

        $cards = [
            $this->card('dashboards.lots_in_stock', $lots->count(), 'fa-boxes', 'primary', area_route('lots.index')),
        ];

        if ($user->role === UserRole::TRANSFORMATEUR) {
            $cards[] = $this->card(
                'dashboards.transformations',
                Transformation::where('transformer_id', $user->id)->count(),
                'fa-industry',
                'success',
                area_route('transformations.index'),
            );
        }

        return view('pages.dashboard', [
            'user' => $user,
            'cards' => $cards,
            'statusChart' => [
                'labels' => array_map(fn (LotStatus $status) => $status->label(), LotStatus::cases()),
                'values' => array_map(fn (LotStatus $status) => $lots->where('status', $status)->count(), LotStatus::cases()),
            ],
            'events' => TraceabilityEvent::where('actor_id', $user->id)->with('lot.product')->latest('id')->limit(6)->get(),
        ]);
    }

    /**
     * @return array{label: string, value: int, icon: string, color: string, url: string}
     */
    private function card(string $label, int $value, string $icon, string $color, string $url): array
    {
        return ['label' => __($label), 'value' => $value, 'icon' => $icon, 'color' => $color, 'url' => $url];
    }
}
