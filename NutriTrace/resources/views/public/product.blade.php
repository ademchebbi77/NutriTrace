@extends('layouts.public')

@section('title', $product->name)
@section('description', \Illuminate\Support\Str::limit((string) $product->description, 150))

@section('content')
    <!-- Product section-->
    <section class="py-5">
        <div class="container px-4 px-lg-5 my-5">
            <div class="row gx-4 gx-lg-5 align-items-center">
                <div class="col-md-6">
                    @if ($product->imageUrl())
                        <img class="card-img-top mb-5 mb-md-0 rounded" src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" />
                    @else
                        <div class="bg-light rounded d-flex align-items-center justify-content-center mb-5 mb-md-0" style="aspect-ratio: 3 / 2;">
                            <i class="bi-image fs-1 text-muted" aria-hidden="true"></i>
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <div class="small mb-1">{{ $product->category->name }}@if ($product->barcode) &middot; {{ __('public.product.barcode') }} {{ $product->barcode }}@endif</div>
                    <h1 class="display-5 fw-bolder">{{ $product->name }}</h1>
                    <div class="fs-5 mb-3">
                        <i class="bi-geo-alt me-1" aria-hidden="true"></i>{{ $product->origin }}
                    </div>
                    <div class="mb-3">
                        @foreach ($product->validCertificationTypes() as $type)
                            <span class="badge bg-success"><i class="bi-patch-check me-1" aria-hidden="true"></i>{{ $type->label() }}</span>
                        @endforeach
                        @if ($product->reviews->isNotEmpty())
                            <span class="badge bg-warning text-dark"><i class="bi-star-fill me-1" aria-hidden="true"></i>{{ __('reviews.average', ['rating' => number_format($averageRating, 1, ',', '')]) }}
                                ({{ trans_choice('reviews.count', $product->reviews->count(), ['count' => $product->reviews->count()]) }})</span>
                        @endif
                    </div>
                    <p class="lead">{{ $product->description }}</p>
                    <p class="mb-4">
                        {{ __('public.product.producer') }} <strong>{{ $product->creator->displayName() }}</strong>
                        @if ($product->creator->organization?->is_verified)
                            <span class="badge bg-success"><i class="bi-check-circle me-1" aria-hidden="true"></i>{{ __('public.product.verified_org') }}</span>
                        @endif
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        @if ($lots->isNotEmpty())
                            <a class="btn btn-dark flex-shrink-0" href="{{ $lots->first()->publicUrl() }}">
                                <i class="bi-signpost-split me-1" aria-hidden="true"></i>{{ __('public.catalog.trace') }}</a>
                        @endif
                        @auth
                            <form method="POST" action="{{ route('favorites.toggle', $product) }}">
                                @csrf
                                <button class="btn btn-outline-dark flex-shrink-0" type="submit">
                                    <i class="bi-heart{{ $isFavorite ? '-fill' : '' }} me-1" aria-hidden="true"></i>
                                    {{ $isFavorite ? __('public.favorites.remove') : __('public.favorites.add') }}
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Lots section-->
    <section class="py-5 bg-light">
        <div class="container px-4 px-lg-5">
            <h2 class="fw-bolder mb-4">{{ __('public.product.lots_title') }}</h2>
            @if ($lots->isEmpty())
                <p class="text-muted">{{ __('public.product.no_lots') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle bg-white">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('public.product.lot') }}</th>
                                <th scope="col">{{ __('public.product.produced') }}</th>
                                <th scope="col">{{ __('public.product.status') }}</th>
                                <th scope="col">{{ __('public.catalog.grade') }}</th>
                                <th scope="col">{{ __('trust.title') }}</th>
                                <th scope="col"><span class="visually-hidden">{{ __('ui.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $lot)
                                <tr>
                                    <th scope="row">{{ $lot->lot_number }}</th>
                                    <td>{{ $lot->production_date->format('d/m/Y') }}</td>
                                    <td>{{ $lot->status->label() }}</td>
                                    <td>
                                        @if ($lot->environmentalImpact?->grade)
                                            <span class="badge grade-badge grade-{{ strtolower($lot->environmentalImpact->grade) }}">{{ $lot->environmentalImpact->grade }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $lot->trust_score !== null ? $lot->trust_score.' / 100' : '—' }}</td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="{{ $lot->publicUrl() }}">{{ __('public.product.open') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
    <!-- Related items section-->
    @if ($related->isNotEmpty())
        <section class="py-5">
            <div class="container px-4 px-lg-5 mt-5">
                <h2 class="fw-bolder mb-4">{{ __('public.product.related') }}</h2>
                <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                    @foreach ($related as $relatedProduct)
                        @include('public.partials.product-card', ['product' => $relatedProduct])
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
