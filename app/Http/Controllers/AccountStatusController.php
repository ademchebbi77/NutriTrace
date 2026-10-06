<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    /**
     * Explain to a pending, rejected or deactivated user why the back office is closed.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->canAccessBackOffice()) {
            return redirect()->route('dashboard');
        }

        return view('auth.account-status', [
            'user' => $request->user(),
        ]);
    }
}
