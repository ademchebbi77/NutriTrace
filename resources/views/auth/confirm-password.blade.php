@extends('layouts.auth')

@section('title', __('account.confirm.title'))
@section('heading', __('account.confirm.heading'))
@section('subheading', __('account.confirm.intro'))

@section('content')
    <form class="user" method="POST" action="{{ route('password.confirm') }}" novalidate>
        @csrf
        <div class="form-group">
            <label for="password">{{ __('account.fields.password') }}</label>
            <input type="password" name="password" id="password"
                class="form-control form-control-user @error('password') is-invalid @enderror" required autofocus
                autocomplete="current-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary btn-user btn-block">
            {{ __('account.confirm.submit') }}
        </button>
    </form>
@endsection
