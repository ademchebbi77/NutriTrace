<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') - {{ config('app.name') }}</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Custom fonts and styles for this template (SB Admin 2) -->
    @vite(['resources/assets/css/admin/app.css'])
    @stack('styles')

</head>

{{-- SB Admin 2 auth card, centred over a full-screen photo. --}}
<body class="auth-page">

    <a class="auth-brand" href="{{ route('home') }}">
        <span class="auth-brand-icon" aria-hidden="true"><i class="fas fa-seedling"></i></span>
        {{ config('app.name') }}
    </a>
    <p class="auth-tagline text-center">{{ __('account.tagline') }}</p>

    <main class="card auth-card border-0 shadow-lg @yield('card-class')">
        <div class="auth-card-header bg-gradient-primary">
            <h1>@yield('heading')</h1>
            @hasSection('subheading')
                <p>@yield('subheading')</p>
            @endif
        </div>

        <div class="auth-card-body">
            @include('partials.flash')

            @yield('content')
        </div>

        @hasSection('footer')
            <div class="auth-card-footer">
                @yield('footer')
            </div>
        @endif
    </main>

    <a class="auth-back" href="{{ route('home') }}">&larr; {{ __('account.login.back_home') }}</a>

    @vite(['resources/assets/js/admin/app.js'])
    @stack('scripts')

</body>

</html>
