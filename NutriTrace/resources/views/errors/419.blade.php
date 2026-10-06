@extends('layouts.auth')

@section('title', __('account.errors.419_title'))
@section('heading', __('account.errors.419_title'))

@section('content')
    <!-- 419 Error Text -->
    <div class="text-center">
        <div class="error mx-auto" data-text="419">419</div>
        <p class="text-gray-700 mt-4 mb-4">{{ __('account.errors.419_text') }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary btn-user btn-block">{{ __('account.errors.back') }}</a>
    </div>
@endsection
