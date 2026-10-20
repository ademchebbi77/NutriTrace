<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\UserRegistrar;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'roles' => UserRole::registrable(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterRequest $request, UserRegistrar $registrar): RedirectResponse
    {
        $user = $registrar->register($request->validated());

        event(new Registered($user));

        Auth::login($user);

        // Redirect consumers to home page, others to their dashboard
        if ($user->role === UserRole::CONSOMMATEUR) {
            return redirect(route('home', absolute: false));
        }

        return redirect(route('dashboard', absolute: false));
    }
}
