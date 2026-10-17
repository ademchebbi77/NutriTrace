<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Services\Ai\CertificationRiskAnalyzer;
use App\Services\CertificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CertificationReviewController extends Controller
{
    public function __construct(
        private readonly CertificationService $certifications,
        private readonly CertificationRiskAnalyzer $risk,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Certification::class);

        $certifications = Certification::with(['certifiable', 'owner.organization'])->latest()->get();
        $analyses = $this->risk->forList($certifications);

        return view('admin.certifications.index', [
            // Pending ones first, the most suspicious at the top: they are waiting for a decision.
            'certifications' => $certifications
                ->sortBy(fn (Certification $certification) => [
                    $certification->status === CertificationStatus::PENDING ? 0 : 1,
                    -($analyses[$certification->id]->risk ?? 0),
                ])
                ->values(),
            'analyses' => $analyses,
        ]);
    }

    public function show(Certification $certification): View
    {
        Gate::authorize('view', $certification);

        return view('certifications.show', [
            'certification' => $certification->load(['certifiable', 'reviewer', 'owner.organization']),
            'analysis' => $this->risk->current($certification) ?? ($this->risk->isAvailable() ? $this->risk->analyze($certification) : null),
            'canExplain' => $this->risk->canExplain(),
        ]);
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
