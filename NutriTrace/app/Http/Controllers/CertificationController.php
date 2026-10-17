<?php

namespace App\Http\Controllers;

use App\Enums\CertificationType;
use App\Http\Requests\CertificationRequest;
use App\Models\Certification;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use App\Services\Ai\CertificationRiskAnalyzer;
use App\Services\CertificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificationController extends Controller
{
    public function __construct(
        private readonly CertificationService $certifications,
        private readonly CertificationRiskAnalyzer $risk,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Certification::class);

        return view('certifications.index', [
            'certifications' => Certification::visibleTo($request->user())->with('certifiable')->latest()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Certification::class);

        return view('certifications.create', $this->formData($request->user(), new Certification));
    }

    public function store(CertificationRequest $request): RedirectResponse
    {
        $certification = $this->certifications->submit(
            $request->user(),
            $this->resolveTarget($request->user(), $request->validated('target')),
            $request->safe()->except(['target', 'document']),
            $request->file('document'),
        );

        return $this->afterSubmission($certification, __('certifications.submitted'));
    }

    public function show(Certification $certification): View
    {
        Gate::authorize('view', $certification);

        return view('certifications.show', [
            'certification' => $certification->load(['certifiable', 'reviewer', 'owner.organization']),
            'analysis' => $this->risk->current($certification),
            'canExplain' => false,
        ]);
    }

    public function edit(Request $request, Certification $certification): View
    {
        Gate::authorize('update', $certification);

        return view('certifications.edit', $this->formData($request->user(), $certification));
    }

    public function update(CertificationRequest $request, Certification $certification): RedirectResponse
    {
        $this->certifications->resubmit($certification, $request->safe()->except(['target', 'document']), $request->file('document'));

        return $this->afterSubmission($certification, __('certifications.resubmitted'));
    }

    public function destroy(Certification $certification): RedirectResponse
    {
        Gate::authorize('delete', $certification);

        $this->certifications->delete($certification);

        return redirect(area_route('certifications.index'))->with('success', __('certifications.deleted'));
    }

    /**
     * The local AI checks the certificate at once, so the owner can fix the problems it
     * finds before the admin reviews it. Never blocks the submission.
     */
    private function afterSubmission(Certification $certification, string $message): RedirectResponse
    {
        $analysis = $this->risk->isAvailable() ? $this->risk->analyze($certification) : null;
        $redirect = redirect(area_route('certifications.show', $certification))->with('success', $message);

        return $analysis?->isSuspicious() ? $redirect->with('warning', __('ai.certification.producer_warning')) : $redirect;
    }

    /**
     * Serve the proof from the private disk to its owner or to an admin.
     */
    public function document(Certification $certification): StreamedResponse
    {
        Gate::authorize('view', $certification);

        return self::streamDocument($certification);
    }

    public static function streamDocument(Certification $certification): StreamedResponse
    {
        $disk = Storage::disk(CertificationService::DISK);

        abort_unless($certification->document_path && $disk->exists($certification->document_path), 404);

        // Inline so PDFs and images open in the browser; never executed as HTML.
        return $disk->response($certification->document_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * The user's own products, and the lots it made or holds.
     *
     * @return array<string, mixed>
     */
    private function formData(User $user, Certification $certification): array
    {
        return [
            'certification' => $certification,
            'types' => CertificationType::cases(),
            'products' => Product::where('created_by', $user->id)->orderBy('name')->get(),
            'lots' => Lot::visibleTo($user)->with(['product', 'production', 'transformation'])->latest()->get()
                ->filter(fn (Lot $lot) => $user->can('certify', $lot))
                ->values(),
        ];
    }

    private function resolveTarget(User $user, string $target): Product|Lot
    {
        [$type, $id] = explode(':', $target);

        $model = $type === 'product' ? Product::find($id) : Lot::find($id);

        $allowed = match (true) {
            $model instanceof Product => $model->isOwnedBy($user),
            $model instanceof Lot => $user->can('certify', $model),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages(['target' => __('certifications.validation.target')]);
        }

        return $model;
    }
}
