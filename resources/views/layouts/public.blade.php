<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="@yield('description', __('ui.meta_description'))" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>@yield('title') - {{ config('app.name') }}</title>
        <!-- Favicon-->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
        <!-- Core theme CSS (Start Bootstrap Landing Page / Shop templates, includes Bootstrap)-->
        @vite(['resources/assets/css/public/app.css'])
        @stack('styles')
    </head>
    <body>
        @include('partials.public-navbar')

        <main>
            @if (collect(['success', 'status', 'warning', 'error', 'info'])->contains(fn ($key) => session()->has($key)))
                <div class="container px-4 px-lg-5 mt-4">
                    @include('partials.flash', ['bootstrap' => 5])
                </div>
            @endif

            @yield('content')
        </main>

        @include('partials.public-footer')

        {{-- Bundled as an ES module: page scripts pushed to the "scripts" stack must use <script type="module">. --}}
        @vite(['resources/assets/js/public/app.js'])
        @stack('scripts')
    </body>
</html>
