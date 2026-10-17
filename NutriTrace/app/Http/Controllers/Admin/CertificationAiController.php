<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Services\Ai\CertificationRiskAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Explanation of a certificate's risk by the local LLM, for the admin who reviews it.
 */
class CertificationAiController extends Controller
{
    public function explain(Certification $certification, CertificationRiskAnalyzer $analyzer): RedirectResponse
    {
        Gate::authorize('view', $certification);

        $analysis = $analyzer->analyze($certification, explain: true);

        return back()->with(
            $analysis?->explanation ? 'success' : 'error',
            $analysis?->explanation ? __('ai.certification.explained') : __('ai.certification.unavailable'),
        );
    }
}
