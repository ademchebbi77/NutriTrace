@extends('layouts.auth')

@section('title', __('account.errors.403_title'))
@section('heading', __('account.errors.403_title'))

@section('content')
    <!-- 403 Error Text -->
    <div class="text-center">
        <div class="error mx-auto" data-text="403">403</div>
        <p class="text-gray-700 mt-4 mb-4">{{ __('account.errors.403_text') }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary btn-user btn-block">{{ __('account.errors.back') }}</a>
    </div>
@endsection
