@extends('layouts.layout')

@section('title', 'Codes de récupération 2FA')

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('Codes de récupération 2FA') }}</h1>
    </div>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="alert alert-success mb-4">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Vos codes de récupération</h3>
                
                <p class="text-sm text-gray-600 mb-4">
                    Conservez ces codes dans un endroit sûr. Ils vous permettront d'accéder à votre compte si vous perdez votre appareil d'authentification.
                </p>

                <div class="bg-gray-50 p-4 rounded mb-6">
                    <div class="grid grid-cols-2 gap-3">
                        @if(session('recovery_codes'))
                            @foreach (session('recovery_codes') as $code)
                                <code class="text-sm font-mono">{{ $code }}</code>
                            @endforeach
                        @else
                            @foreach ($recoveryCodes as $code)
                                <code class="text-sm font-mono">{{ $code }}</code>
                            @endforeach
                        @endif
                    </div>
                </div>

                <p class="text-sm text-red-600 mb-6">
                    ⚠️ Chaque code ne peut être utilisé qu'une seule fois.
                </p>

                <div class="flex items-center gap-4">
                    <button onclick="window.print()" class="btn btn-secondary">
                        🖨️ Imprimer
                    </button>

                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <label for="regenerate-password" class="sr-only">Mot de passe</label>
                        <input type="password" id="regenerate-password" name="password" placeholder="Mot de passe" required 
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="submit" class="btn btn-warning ml-2"
                            onclick="return confirm('Cela invalidera tous les codes précédents. Continuer?')">
                            🔄 Régénérer les codes
                        </button>
                    </form>
                </div>

                <div class="mt-6">
                    <a href="{{ route('two-factor.show') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
                        ← Retour aux paramètres 2FA
                    </a>
                </div>
            </div>
        </div>
    </div>

@endsection
