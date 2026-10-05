<?php

namespace App\Http\Controllers;

use App\Enums\DistributionStatus;
use App\Enums\EventType;
use App\Enums\LotStatus;
use App\Enums\TransferStatus;
use App\Enums\TransportStatus;
use App\Enums\UserRole;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Models\TraceabilityEvent;
use App\Models\Transformation;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard of transformers and distributors: counters for what needs attention,
 * the state of their lots and their latest activity.
 */
class RoleDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('organization');
        $lots = Lot::heldBy($user)->get(['id', 'status']);

        return view('pages.dashboard', [
            'user' => $user,
            'cards' => $user->role === UserRole::DISTRIBUTEUR ? $this->distributorCards($user, $lots) : $this->transformerCards($user, $lots),
            'statusChart' => [
                'labels' => array_map(fn (LotStatus $status) => $status->label(), LotStatus::cases()),
                'values' => array_map(fn (LotStatus $status) => $lots->where('status', $status)->count(), LotStatus::cases()),
            ],
            'events' => TraceabilityEvent::where('actor_id', $user->id)->with('lot.product')->latest('id')->limit(6)->get(),
        ]);
    }

    /**
     * @return list<array{label: string, value: int, icon: string, color: string, url: string}>
     */
    private function transformerCards(User $user, $lots): array
    {
        return [
            $this->card('dashboards.pending_receptions', LotTransfer::where('to_user_id', $user->id)->where('status', TransferStatus::PENDING)->count(), 'fa-inbox', 'warning', 'transformateur.receptions.index'),
            $this->card('dashboards.lots_in_stock', $lots->filter(fn (Lot $lot) => in_array($lot->status, [LotStatus::CREATED, LotStatus::IN_TRANSFORMATION], true))->count(), 'fa-boxes', 'primary', 'transformateur.lots.index'),
            $this->card('dashboards.transformations', Transformation::where('transformer_id', $user->id)->count(), 'fa-industry', 'success', 'transformateur.transformations.index'),
            $this->card('dashboards.in_transit', Transport::where('shipper_id', $user->id)->where('status', TransportStatus::IN_TRANSIT)->count(), 'fa-truck', 'info', 'transformateur.transports.index'),
        ];
    }

    /**
     * @return list<array{label: string, value: int, icon: string, color: string, url: string}>
     */
    private function distributorCards(User $user, $lots): array
    {
        return [
            $this->card('dashboards.pending_receptions', Distribution::where('distributor_id', $user->id)->where('status', DistributionStatus::PENDING)->count(), 'fa-inbox', 'warning', 'distributeur.receptions.index'),
            $this->card('dashboards.to_shelve', Distribution::where('distributor_id', $user->id)->where('status', DistributionStatus::RECEIVED)->count(), 'fa-dolly', 'primary', 'distributeur.distributions.index'),
            $this->card('dashboards.in_store', $lots->where('status', LotStatus::IN_STORE)->count(), 'fa-store', 'success', 'distributeur.sales.index'),
            $this->card('dashboards.sales', TraceabilityEvent::where('actor_id', $user->id)->where('event_type', EventType::SALE)->count(), 'fa-cash-register', 'info', 'distributeur.sales.index'),
        ];
    }

    /**
     * @return array{label: string, value: int, icon: string, color: string, url: string}
     */
    private function card(string $label, int $value, string $icon, string $color, string $route): array
    {
        return ['label' => __($label), 'value' => $value, 'icon' => $icon, 'color' => $color, 'url' => route($route)];
    }
}
