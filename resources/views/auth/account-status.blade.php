@extends('layouts.auth')

@section('title', __('account.status.title'))

@php
    $state = match (true) {
        $user->isPending() => ['pending', 'fa-hourglass-half', 'warning'],
        $user->account_status === \App\Enums\AccountStatus::REJECTED => ['rejected', 'fa-times-circle', 'danger'],
        default => ['inactive', 'fa-ban', 'secondary'],
    };
@endphp

@section('heading', __("account.status.{$state[0]}_heading"))

@section('content')
    <div class="text-center" role="status">
        <i class="fas {{ $state[1] }} fa-3x text-{{ $state[2] }} mb-3" aria-hidden="true"></i>
        <p class="mb-4">{{ __("account.status.{$state[0]}_body") }}</p>
        @if ($state[0] === 'rejected' && $user->rejection_reason)
            <div class="alert alert-danger text-left">
                <strong>{{ __('account.status.rejected_reason') }}</strong>
                {{ $user->rejection_reason }}
            </div>
        @endif
    </div>
    <a href="{{ route('home') }}" class="btn btn-primary btn-user btn-block">
        {{ __('account.login.back_home') }}
    </a>
    <div class="text-center mt-3">
        <a class="small" href="{{ route('profile.edit') }}">{{ __('account.status.profile') }}</a>
    </div>
@endsection

@section('footer')
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-link btn-sm font-weight-bold">{{ __('ui.topbar.logout') }}</button>
    </form>
@endsection
