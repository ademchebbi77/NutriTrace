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
                    <p class="lead">{{ $product->description }}</p>
                    <p class="mb-4">
                        {{ __('public.product.producer') }} <strong>{{ $product->creator->displayName() }}</strong>
                        @if ($product->creator->organization?->is_verified)
                            <span class="badge bg-success"><i class="bi-check-circle me-1" aria-hidden="true"></i>{{ __('public.product.verified_org') }}</span>
                        @endif
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        @auth
                            <form method="POST" action="{{ route('favorites.toggle', $product) }}">
                                @csrf
                                <button class="btn btn-outline-dark flex-shrink-0" type="submit">
                                    <i class="bi-heart me-1" aria-hidden="true"></i>
                                    {{ __('public.favorites.add') }}
                                </button>
                            </form>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Productions section-->
    <section class="py-5 bg-light">
        <div class="container px-4 px-lg-5">
            <h2 class="fw-bolder mb-4">{{ __('public.product.productions_title', [], 'fr') ?? 'Productions' }}</h2>
            @if ($product->productions->isEmpty())
                <p class="text-muted">{{ __('public.product.no_productions', [], 'fr') ?? 'Aucune production enregistrée.' }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle bg-white">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('productions.fields.production_date') }}</th>
                                <th scope="col">{{ __('productions.fields.quantity') }}</th>
                                <th scope="col">{{ __('productions.fields.production_method') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($product->productions->sortByDesc('production_date') as $production)
                                <tr>
                                    <td>{{ $production->production_date->format('d/m/Y') }}</td>
                                    <td>{{ $production->quantity }} {{ $production->unit?->value }}</td>
                                    <td>{{ $production->production_method?->label() ?? '—' }}</td>
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
