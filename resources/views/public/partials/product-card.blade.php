{{-- Shop Homepage product card. Expects $product with category loaded. --}}
<div class="col mb-5">
    <div class="card h-100">
        <!-- Product image-->
        @if ($product->imageUrl())
            <img class="card-img-top" src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" />
        @else
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="aspect-ratio: 3 / 2;">
                <i class="bi-image fs-1 text-muted" aria-hidden="true"></i>
            </div>
        @endif
        <!-- Product details-->
        <div class="card-body p-4">
            <div class="text-center">
                <div class="small text-muted mb-1">{{ $product->category->name }}</div>
                <h3 class="h5 fw-bolder">{{ $product->name }}</h3>
                <div class="small text-muted mb-2">
                    <i class="bi-geo-alt me-1" aria-hidden="true"></i>{{ $product->origin }}
                </div>
                @if ($product->description)
                    <p class="small text-muted mb-0">{{ Str::limit($product->description, 80) }}</p>
                @endif
            </div>
        </div>
        <!-- Product actions-->
        <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
            <div class="text-center">
                <a class="btn btn-outline-dark mt-auto stretched-link" href="{{ route('catalog.show', $product) }}">
                    {{ __('public.catalog.view') }}
                </a>
            </div>
        </div>
    </div>
</div>
