<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip 2FA check for 2FA routes themselves (avoid redirect loop)
        if ($request->routeIs('two-factor.*')) {
            return $next($request);
        }

        // If user has 2FA enabled but hasn't verified in this session
        if ($user && $user->two_factor_confirmed_at && !$request->session()->get('two_factor_verified')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
