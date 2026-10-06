<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectAccountRequest;
use App\Models\User;
use App\Services\AccountApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private readonly AccountApprovalService $approvals) {}

    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.approvals.index', [
            'users' => User::pendingApproval()->with('organization')->latest()->get(),
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('review', $user);

        $this->approvals->approve($user, $request->user());

        return back()->with('success', __('account.admin.approved', ['name' => $user->name]));
    }

    public function reject(RejectAccountRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('review', $user);

        $this->approvals->reject($user, $request->user(), $request->validated('rejection_reason'));

        return back()->with('success', __('account.admin.rejected', ['name' => $user->name]));
    }
}
