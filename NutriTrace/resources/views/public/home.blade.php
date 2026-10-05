@extends('layouts.public')

@section('title', __('public.home.title'))

@section('content')
    <!-- Masthead-->
    <header class="masthead">
        <div class="container position-relative">
            <div class="row justify-content-center">
                <div class="col-xl-7">
                    <div class="text-center text-white">
                        <!-- Page heading-->
                        <h1 class="mb-3">{{ __('public.home.hero') }}</h1>
                        <p class="lead mb-5">{{ __('public.home.hero_sub') }}</p>
                        <!-- Search form-->
                        <form class="form-subscribe" method="GET" action="{{ route('lookup') }}" role="search">
                            <div class="row">
                                <div class="col">
                                    <label class="visually-hidden" for="heroSearch">{{ __('public.home.search_placeholder') }}</label>
                                    <input class="form-control form-control-lg" id="heroSearch" name="q" type="search" placeholder="{{ __('public.home.search_placeholder') }}" />
                                </div>
                                <div class="col-auto"><button class="btn btn-primary btn-lg" type="submit">{{ __('public.home.search') }}</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- Icons Grid-->
    <section class="features-icons bg-light text-center">
        <div class="container">
            <div class="row">
                @foreach (['traceability' => 'bi-signpost-split', 'footprint' => 'bi-globe-europe-africa', 'certifications' => 'bi-patch-check', 'trust' => 'bi-shield-check'] as $feature => $icon)
                    <div class="col-lg-3 col-md-6">
                        <div class="features-icons-item mx-auto mb-5 mb-lg-0 mb-lg-3">
                            <div class="features-icons-icon d-flex"><i class="{{ $icon }} m-auto text-primary" aria-hidden="true"></i></div>
                            <h2 class="h4">{{ __("public.home.features.$feature.title") }}</h2>
                            <p class="mb-0">{{ __("public.home.features.$feature.text") }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <!-- Image Showcases-->
    <section class="showcase">
        <div class="container-fluid p-0">
            @foreach (__('public.home.showcases') as $index => $showcase)
                <div class="row g-0">
                    <div class="col-lg-6 {{ $index % 2 === 0 ? 'order-lg-2' : '' }} text-white showcase-img showcase-tile showcase-tile-{{ $index + 1 }} d-flex align-items-center justify-content-center">
                        <i class="{{ $showcase['icon'] }}" aria-hidden="true"></i>
                    </div>
                    <div class="col-lg-6 {{ $index % 2 === 0 ? 'order-lg-1' : '' }} my-auto showcase-text">
                        <h2>{{ $showcase['title'] }}</h2>
                        <p class="lead mb-0">{{ $showcase['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    <!-- Key figures-->
    <section class="testimonials text-center bg-light">
        <div class="container">
            <div class="row">
                @foreach ($stats as $key => $value)
                    <div class="col-lg-4">
                        <div class="testimonial-item mx-auto mb-5 mb-lg-0">
                            <div class="display-4 fw-bolder text-primary">{{ $value }}</div>
                            <p class="font-weight-light mb-0">{{ __('public.home.stats.'.$key) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    <!-- Featured products (Shop Homepage cards)-->
    @if ($featured->isNotEmpty())
        <section class="py-5">
            <div class="container px-4 px-lg-5 mt-5">
                <h2 class="fw-bolder mb-4">{{ __('public.home.featured') }}</h2>
                <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                    @foreach ($featured as $product)
                        @include('public.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
                <div class="text-center">
                    <a class="btn btn-outline-dark" href="{{ route('catalog.index') }}">{{ __('public.home.see_catalog') }}</a>
                </div>
            </div>
        </section>
    @endif
    <!-- Call to Action-->
    <section class="call-to-action text-white text-center" id="signup">
        <div class="container position-relative">
            <div class="row justify-content-center">
                <div class="col-xl-8">
                    <h2 class="mb-4">{{ __('public.home.cta') }}</h2>
                    @guest
                        <a class="btn btn-primary btn-lg m-1" href="{{ route('register') }}">{{ __('public.home.cta_button') }}</a>
                        <a class="btn btn-light btn-lg m-1" href="{{ route('register') }}">{{ __('public.home.cta_consumer') }}</a>
                    @else
                        <a class="btn btn-primary btn-lg" href="{{ route('dashboard') }}">{{ __('ui.nav.my_space') }}</a>
                    @endguest
                </div>
            </div>
        </div>
    </section>
@endsection
