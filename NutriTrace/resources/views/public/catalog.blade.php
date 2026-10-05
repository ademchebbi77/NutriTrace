@extends('layouts.public')

@section('title', __('public.catalog.title'))

@section('content')
    <!-- Header-->
    <header class="bg-dark py-5">
        <div class="container px-4 px-lg-5 my-4">
            <div class="text-center text-white">
                <h1 class="display-5 fw-bolder">{{ __('public.catalog.heading') }}</h1>
                <p class="lead fw-normal text-white-50 mb-0">{{ __('public.catalog.sub') }}</p>
            </div>
        </div>
    </header>
    <!-- Section-->
    <section class="py-5">
        <div class="container px-4 px-lg-5">
            <!-- Filters-->
            <form method="GET" action="{{ route('catalog.index') }}" class="card card-body bg-light border-0 mb-5" role="search">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label" for="q">{{ __('public.catalog.search') }}</label>
                        <input class="form-control" type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('public.catalog.search_placeholder') }}" />
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label" for="category">{{ __('public.catalog.category') }}</label>
                        <select class="form-select" name="category" id="category">
                            <option value="">{{ __('public.catalog.all') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) ($filters['category'] ?? 0) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label" for="certification">{{ __('public.catalog.certification') }}</label>
                        <select class="form-select" name="certification" id="certification">
                            <option value="">{{ __('public.catalog.all') }}</option>
                            @foreach ($certificationTypes as $type)
                                <option value="{{ $type->value }}" @selected(($filters['certification'] ?? '') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label" for="grade">{{ __('public.catalog.grade') }}</label>
                        <select class="form-select" name="grade" id="grade">
                            <option value="">{{ __('public.catalog.all') }}</option>
                            @foreach (['A', 'B', 'C', 'D', 'E'] as $grade)
                                <option value="{{ $grade }}" @selected(($filters['grade'] ?? '') === $grade)>{{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label" for="origin">{{ __('public.catalog.origin') }}</label>
                        <select class="form-select" name="origin" id="origin">
                            <option value="">{{ __('public.catalog.all') }}</option>
                            @foreach ($origins as $origin)
                                <option value="{{ $origin }}" @selected(($filters['origin'] ?? '') === $origin)>{{ $origin }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-1 col-12 d-grid">
                        <button class="btn btn-dark" type="submit">{{ __('public.catalog.filter') }}</button>
                    </div>
                </div>
                @if (collect($filters)->filter()->isNotEmpty())
                    <div class="mt-3">
                        <a href="{{ route('catalog.index') }}" class="small"><i class="bi-x-circle me-1" aria-hidden="true"></i>{{ __('public.catalog.reset') }}</a>
                    </div>
                @endif
            </form>

            <p class="text-muted" role="status">{{ trans_choice('public.catalog.results', $products->total(), ['count' => $products->total()]) }}</p>

            @if ($products->isEmpty())
                <div class="text-center py-5">
                    <i class="bi-search fs-1 text-muted" aria-hidden="true"></i>
                    <p class="lead mt-3">{{ __('public.catalog.empty') }}</p>
                </div>
            @else
                <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                    @foreach ($products as $product)
                        @include('public.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
                <div class="d-flex justify-content-center">
                    {{ $products->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
@endsection
