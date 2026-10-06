<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('organization');

        return view('pages.dashboard', [
            'user' => $user,
        ]);
    }
}
