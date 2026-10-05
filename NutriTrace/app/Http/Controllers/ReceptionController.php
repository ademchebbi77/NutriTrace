<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\RejectHandoverRequest;
use App\Models\Distribution;
use App\Models\Lot;
use App\Models\LotTransfer;
use App\Services\LotReceptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Receiver's side of a hand-over. Transformers receive transfers,
 * distributors receive distributions; the holder changes on confirmation.
 */
class ReceptionController extends Controller
{
    public function __construct(private readonly LotReceptionService $receptions) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lot::class);

        $user = $request->user();

        if ($user->role === UserRole::DISTRIBUTEUR) {
            $incoming = Distribution::where('distributor_id', $user->id)
                ->with(['lot.product', 'sender.organization', 'transport'])
                ->latest()
                ->get()
                ->map(fn (Distribution $distribution) => [
                    'lot' => $distribution->lot,
                    'sender' => $distribution->sender,
                    'transport' => $distribution->transport,
                    'quantity' => $distribution->quantity,
                    'note' => null,
                    'sent_at' => $distribution->created_at,
                    'pending' => $distribution->isPending(),
                    'status_label' => $distribution->status->label(),
                    'status_color' => $distribution->status->color(),
                    'accept_url' => route('distributeur.distributions.receive', $distribution),
                    'reject_url' => route('distributeur.distributions.reject', $distribution),
                ]);
        } else {
            $incoming = LotTransfer::where('to_user_id', $user->id)
                ->with(['lot.product', 'sender.organization', 'transport'])
                ->latest()
                ->get()
                ->map(fn (LotTransfer $transfer) => [
                    'lot' => $transfer->lot,
                    'sender' => $transfer->sender,
                    'transport' => $transfer->transport,
                    'quantity' => $transfer->quantity,
                    'note' => $transfer->note,
                    'sent_at' => $transfer->created_at,
                    'pending' => $transfer->isPending(),
                    'status_label' => $transfer->status->label(),
                    'status_color' => $transfer->status->color(),
                    'accept_url' => route('transformateur.receptions.accept', $transfer),
                    'reject_url' => route('transformateur.receptions.reject', $transfer),
                ]);
        }

        return view('receptions.index', [
            'pending' => $incoming->where('pending', true)->values(),
            'history' => $incoming->where('pending', false)->values(),
        ]);
    }

    public function accept(LotTransfer $transfer): RedirectResponse
    {
        Gate::authorize('respond', $transfer);

        $this->receptions->acceptTransfer($transfer);

        return back()->with('success', __('transfers.received', ['lot' => $transfer->lot->lot_number]));
    }

    public function reject(RejectHandoverRequest $request, LotTransfer $transfer): RedirectResponse
    {
        Gate::authorize('respond', $transfer);

        $this->receptions->rejectTransfer($transfer, $request->validated('rejection_reason'));

        return back()->with('success', __('transfers.refused', ['lot' => $transfer->lot->lot_number]));
    }
}
