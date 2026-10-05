<?php

namespace App\Http\Controllers;

use App\Enums\TransportType;
use App\Enums\UserRole;
use App\Http\Requests\SendLotRequest;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Models\User;
use App\Services\LotDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Sender's side of a hand-over: send a lot, follow what was sent.
 */
class TransferController extends Controller
{
    /**
     * Everything the user sent, to transformers (transfers) and to distributors (distributions).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lot::class);

        $user = $request->user();

        $transfers = LotTransfer::where('from_user_id', $user->id)
            ->with(['lot.product', 'recipient.organization', 'transport'])
            ->get()
            ->map(fn (LotTransfer $transfer) => [
                'lot' => $transfer->lot,
                'recipient' => $transfer->recipient,
                'transport' => $transfer->transport,
                'quantity' => $transfer->quantity,
                'sent_at' => $transfer->created_at,
                'status_label' => $transfer->status->label(),
                'status_color' => $transfer->status->color(),
                'rejection_reason' => $transfer->rejection_reason,
            ]);

        $distributions = Distribution::where('sender_id', $user->id)
            ->with(['lot.product', 'distributor.organization', 'transport'])
            ->get()
            ->map(fn (Distribution $distribution) => [
                'lot' => $distribution->lot,
                'recipient' => $distribution->distributor,
                'transport' => $distribution->transport,
                'quantity' => $distribution->quantity,
                'sent_at' => $distribution->created_at,
                'status_label' => $distribution->status->label(),
                'status_color' => $distribution->status->color(),
                'rejection_reason' => $distribution->rejection_reason,
            ]);

        return view('transfers.index', [
            'handovers' => $transfers->concat($distributions)->sortByDesc('sent_at')->values(),
            'sendable' => Lot::heldBy($user)->with('product')->get()->filter->isAvailable()->values(),
        ]);
    }

    public function create(Request $request, Lot $lot): View
    {
        Gate::authorize('send', $lot);

        return view('transfers.create', [
            'lot' => $lot->load('product', 'production'),
            'recipients' => User::operational()
                ->whereIn('role', [UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR])
                ->whereKeyNot($request->user()->id)
                ->with('organization')
                ->get()
                ->sortBy(fn (User $user) => $user->displayName())
                ->groupBy(fn (User $user) => $user->role->label()),
            'transportTypes' => TransportType::cases(),
        ]);
    }

    public function store(SendLotRequest $request, Lot $lot, LotDispatcher $dispatcher): RedirectResponse
    {
        $recipient = User::with('organization')->findOrFail($request->validated('recipient_id'));

        $dispatcher->send($lot, $request->user(), $recipient, $request->validated());

        return redirect(area_route('transfers.index'))
            ->with('success', __('transfers.sent', ['lot' => $lot->lot_number, 'recipient' => $recipient->displayName()]));
    }
}
