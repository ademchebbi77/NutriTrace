<?php

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Services\Ai\ReceptionAdvisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Local AI check of a lot before the distributor accepts it.
 */
class DistributionAiController extends Controller
{
    public function check(Distribution $distribution, ReceptionAdvisor $advisor): RedirectResponse
    {
        Gate::authorize('respond', $distribution);

        $check = $advisor->check($distribution);

        return redirect()->route('distributeur.distributions.show', $distribution)->with(
            $check ? 'success' : 'error',
            $check ? __('ai.reception.done') : __('ai.reception.unavailable'),
        );
    }
}
