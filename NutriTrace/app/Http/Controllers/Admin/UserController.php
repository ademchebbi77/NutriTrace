<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccountApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AccountApprovalService $approvals) {}

    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.users.index', [
            'users' => User::with('organization')->latest()->get(),
        ]);
    }

    public function toggleActive(User $user): RedirectResponse
    {
        Gate::authorize('toggleActive', $user);

        $this->approvals->setActive($user, ! $user->is_active);

        return back()->with('success', __($user->is_active ? 'account.admin.activated' : 'account.admin.deactivated', ['name' => $user->name]));
    }

    public function toggleVerified(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('verify', $organization);

        $this->approvals->setOrganizationVerified($organization, $request->user(), ! $organization->is_verified);

        return back()->with('success', __($organization->is_verified ? 'account.admin.org_verified' : 'account.admin.org_unverified', ['name' => $organization->name]));
    }
}
