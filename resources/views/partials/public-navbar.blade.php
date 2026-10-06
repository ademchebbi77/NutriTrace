@php
    $links = [
        ['label' => __('ui.nav.home'), 'route' => 'home'],
        ['label' => __('ui.nav.catalog'), 'route' => 'catalog.index'],
        ['label' => __('ui.nav.compare'), 'route' => 'compare'],
        ['label' => __('ui.nav.how_it_works'), 'route' => 'how-it-works'],
    ];
@endphp
<!-- Navigation-->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container px-4 px-lg-5">
        <a class="navbar-brand" href="{{ route('home') }}"><i class="bi-flower1 me-1"></i>{{ config('app.name') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('ui.toggle_navigation') }}"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                @foreach ($links as $link)
                    @if (Route::has($link['route']))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs($link['route']) ? 'active' : '' }}" @if (request()->routeIs($link['route'])) aria-current="page" @endif href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>
            <div class="d-flex gap-2">
                @auth
                    <a class="btn btn-outline-dark" href="{{ route('dashboard') }}">
                        <i class="bi-person-fill me-1"></i>
                        {{ __('ui.nav.my_space') }}
                    </a>
                @else
                    @if (Route::has('login'))
                        <a class="btn btn-outline-dark" href="{{ route('login') }}">
                            <i class="bi-box-arrow-in-right me-1"></i>
                            {{ __('ui.nav.login') }}
                        </a>
                    @endif
                    @if (Route::has('register'))
                        <a class="btn btn-primary" href="{{ route('register') }}">{{ __('ui.nav.register') }}</a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</nav>
