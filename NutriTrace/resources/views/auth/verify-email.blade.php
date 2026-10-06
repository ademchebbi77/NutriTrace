@extends('layouts.auth')

@section('title', __('account.verify.title'))
@section('heading', __('account.verify.heading'))

@section('content')
    <div class="text-center">
        <i class="fas fa-envelope-open-text fa-3x text-primary mb-3" aria-hidden="true"></i>
        <p class="mb-4">{{ __('account.verify.intro') }}</p>
    </div>
    <form class="user" method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary btn-user btn-block">
            {{ __('account.verify.resend') }}
        </button>
    </form>
@endsection

@section('footer')
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-link btn-sm font-weight-bold">{{ __('ui.topbar.logout') }}</button>
    </form>
@endsection
