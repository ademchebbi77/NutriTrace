<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Pending, rejected or deactivated accounts are sent to the account status page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->canAccessBackOffice()) {
            return $request->expectsJson()
                ? abort(403, __('account.status.blocked'))
                : redirect()->route('account.status');
        }

        return $next($request);
    }
}
