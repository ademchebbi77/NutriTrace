@extends('layouts.auth')

@section('title', __('account.login.title'))
@section('heading', __('account.login.title'))
@section('subheading', __('account.login.sub'))

@section('content')
    <form class="user" method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="form-group">
            <label for="email">{{ __('account.fields.email') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                class="form-control form-control-user @error('email') is-invalid @enderror" required autofocus
                autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="password">{{ __('account.fields.password') }}</label>
            <input type="password" name="password" id="password"
                class="form-control form-control-user @error('password') is-invalid @enderror" required
                autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <div class="custom-control custom-checkbox small">
                <input type="checkbox" class="custom-control-input" id="remember" name="remember" @checked(old('remember'))>
                <label class="custom-control-label font-weight-normal" for="remember">{{ __('account.login.remember') }}</label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-user btn-block">
            {{ __('account.login.submit') }}
        </button>
    </form>
    <hr>
    <div class="text-center">
        <a class="small" href="{{ route('password.request') }}">{{ __('account.login.forgot') }}</a>
    </div>
@endsection

@section('footer')
    {{ __('account.login.no_account') }} <a href="{{ route('register') }}">{{ __('account.login.register') }}</a>
@endsection
