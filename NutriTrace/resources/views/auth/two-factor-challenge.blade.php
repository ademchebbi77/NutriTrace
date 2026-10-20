@extends('layouts.auth')

@section('title', 'Authentification à deux facteurs')
@section('heading', 'Authentification à deux facteurs')
@section('subheading', 'Veuillez confirmer l\'accès à votre compte en entrant le code d\'authentification.')

@section('content')
    <form class="user" method="POST" action="{{ route('two-factor.verify') }}">
        @csrf

        <div class="form-group">
            <label for="code">Code d'authentification</label>
            <input id="code" type="text" name="code" 
                class="form-control form-control-user @error('code') is-invalid @enderror" 
                placeholder="Entrez le code à 6 chiffres" 
                required autofocus autocomplete="one-time-code" maxlength="6">
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-user btn-block">
            Vérifier
        </button>
    </form>
    
    <hr>
    <div class="text-center">
        <p class="small text-muted">
            Vous avez perdu votre appareil? Utilisez un de vos codes de récupération.
        </p>
    </div>
@endsection
