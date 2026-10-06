@extends('layouts.auth')

@section('title', __('account.forgot.title'))
@section('heading', __('account.forgot.title'))
@section('subheading', __('account.forgot.intro'))

@section('content')
    <form class="user" method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="form-group">
            <label for="email">{{ __('account.fields.email') }}</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}"
                class="form-control form-control-user @error('email') is-invalid @enderror" required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary btn-user btn-block">
            {{ __('account.forgot.submit') }}
        </button>
    </form>
@endsection

@section('footer')
    <a href="{{ route('login') }}">{{ __('account.login.submit') }}</a>
    &middot;
    <a href="{{ route('register') }}">{{ __('account.login.register') }}</a>
@endsection
