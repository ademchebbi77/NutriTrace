@extends('layouts.auth')

@section('title', __('account.reset.title'))
@section('heading', __('account.reset.title'))
@section('subheading', __('account.reset.heading'))

@section('content')
    <form class="user" method="POST" action="{{ route('password.store') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="form-group">
            <label for="email">{{ __('account.fields.email') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email', $request->email) }}"
                class="form-control form-control-user @error('email') is-invalid @enderror" required autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="password">{{ __('account.fields.new_password') }}</label>
            <input type="password" name="password" id="password"
                class="form-control form-control-user @error('password') is-invalid @enderror" required autofocus
                autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="password_confirmation">{{ __('account.fields.password_confirmation') }}</label>
            <input type="password" name="password_confirmation" id="password_confirmation"
                class="form-control form-control-user" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-user btn-block">
            {{ __('account.reset.submit') }}
        </button>
    </form>
@endsection
