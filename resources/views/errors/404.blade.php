@extends('layouts.auth')

@section('title', __('account.errors.404_title'))
@section('heading', __('account.errors.404_title'))

@section('content')
    <!-- 404 Error Text -->
    <div class="text-center">
        <div class="error mx-auto" data-text="404">404</div>
        <p class="text-gray-700 mt-4 mb-4">{{ __('account.errors.404_text') }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary btn-user btn-block">{{ __('account.errors.back') }}</a>
    </div>
@endsection
