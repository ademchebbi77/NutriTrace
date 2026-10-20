@extends('layouts.layout')

@section('title', 'Authentification à deux facteurs')

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('Authentification à deux facteurs') }}</h1>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if (!$enabled)
        <!-- Enable 2FA Section -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Activer l'authentification à deux facteurs</h6>
            </div>
            <div class="card-body">
                <p class="mb-4">
                    Ajoutez une couche de sécurité supplémentaire à votre compte en activant l'authentification à deux facteurs.
                </p>

                <h5 class="font-weight-bold">1. Scannez ce QR code avec votre application d'authentification</h5>
                <p class="text-muted small mb-3">
                    Utilisez Google Authenticator, Authy, Microsoft Authenticator ou une autre application TOTP.
                </p>
                
                <div class="bg-white p-3 d-inline-block border rounded mb-3">
                    {!! QrCode::size(200)->generate($qrCodeUrl) !!}
                </div>

                <p class="mb-4">
                    Ou entrez manuellement cette clé: <code class="bg-light px-2 py-1 rounded">{{ $secret }}</code>
                </p>

                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <div class="form-group">
                        <label for="code" class="font-weight-bold">2. Entrez le code à 6 chiffres</label>
                        <input id="code" type="text" name="code" 
                            class="form-control @error('code') is-invalid @enderror" 
                            placeholder="000000" maxlength="6" required autofocus>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-shield-alt"></i> Activer 2FA
                    </button>
                </form>
            </div>
        </div>

        @if (session('recovery_codes'))
            <!-- Show Recovery Codes After Enabling -->
            <div class="card shadow mb-4 border-warning">
                <div class="card-header py-3 bg-warning">
                    <h6 class="m-0 font-weight-bold text-white">⚠️ Codes de récupération</h6>
                </div>
                <div class="card-body">
                    <p class="text-danger font-weight-bold">
                        Important: Conservez ces codes dans un endroit sûr. Ils vous permettront d'accéder à votre compte si vous perdez votre appareil d'authentification.
                    </p>

                    <div class="bg-light p-3 rounded">
                        <div class="row">
                            @foreach (session('recovery_codes') as $code)
                                <div class="col-md-6">
                                    <code class="d-block mb-2">{{ $code }}</code>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <p class="mt-3 text-danger small">
                        ⚠️ Chaque code ne peut être utilisé qu'une seule fois.
                    </p>
                </div>
            </div>
        @endif
    @else
        <!-- 2FA Enabled Section -->
        <div class="card shadow mb-4 border-success">
            <div class="card-header py-3 bg-success">
                <h6 class="m-0 font-weight-bold text-white">✅ Authentification à deux facteurs activée</h6>
            </div>
            <div class="card-body">
                <p class="mb-4">
                    Votre compte est protégé par l'authentification à deux facteurs.
                </p>

                <a href="{{ route('two-factor.recovery-codes') }}" class="btn btn-info mb-3">
                    <i class="fas fa-key"></i> Voir les codes de récupération
                </a>

                <hr>

                <h6 class="font-weight-bold">Désactiver 2FA</h6>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" name="password" id="password" required 
                            class="form-control @error('password') is-invalid @enderror">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-danger" 
                        onclick="return confirm('Êtes-vous sûr de vouloir désactiver 2FA?')">
                        <i class="fas fa-times-circle"></i> Désactiver 2FA
                    </button>
                </form>
            </div>
        </div>
    @endif

@endsection
