<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Send each user to the dashboard of their own role.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->dashboardRoute());
    }
}
