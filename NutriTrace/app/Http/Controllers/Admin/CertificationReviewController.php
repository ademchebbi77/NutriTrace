<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Services\CertificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CertificationReviewController extends Controller
{
    public function __construct(private readonly CertificationService $certifications) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Certification::class);

        return view('admin.certifications.index', [
            // Pending ones first: they are waiting for a decision.
            'certifications' => Certification::with(['certifiable', 'owner.organization'])
                ->latest()
                ->get()
                ->sortBy(fn (Certification $certification) => $certification->status === CertificationStatus::PENDING ? 0 : 1)
                ->values(),
        ]);
    }

    public function show(Certification $certification): View
    {
        Gate::authorize('view', $certification);

        return view('certifications.show', ['certification' => $certification->load(['certifiable', 'reviewer', 'owner.organization'])]);
    }

    public function approve(Request $request, Certification $certification): RedirectResponse
    {
        Gate::authorize('review', $certification);

        $this->certifications->approve($certification, $request->user());

        return redirect()->route('admin.certifications.index')->with('success', __('certifications.admin.approved', ['name' => $certification->name]));
    }

    public function reject(Request $request, Certification $certification): RedirectResponse
    {
        Gate::authorize('review', $certification);

        $data = $request->validate(
            ['rejection_reason' => ['required', 'string', 'min:5', 'max:500']],
            [],
            ['rejection_reason' => __('certifications.attributes.rejection_reason')],
        );

        $this->certifications->reject($certification, $request->user(), $data['rejection_reason']);

        return redirect()->route('admin.certifications.index')->with('success', __('certifications.admin.rejected', ['name' => $certification->name]));
    }
}
