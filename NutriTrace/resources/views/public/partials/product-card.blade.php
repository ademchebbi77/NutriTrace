{{-- Shop Homepage product card. Expects $product with category, certifications, lots.environmentalImpact, lots.certifications. --}}
@php
    $showcase = $product->showcaseLot();
    $grade = $showcase?->environmentalImpact?->grade;
@endphp
<div class="col mb-5">
    <div class="card h-100">
        <!-- Grade badge-->
        @if ($grade)
            <div class="badge grade-badge grade-{{ strtolower($grade) }} position-absolute" style="top: 0.5rem; right: 0.5rem">
                {{ __('public.catalog.grade_badge', ['grade' => $grade]) }}</div>
        @endif
        <!-- Product image-->
        @if ($product->imageUrl())
            <img class="card-img-top" src="{{ $product->imageUrl() }}" alt="" />
        @else
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="aspect-ratio: 3 / 2;">
                <i class="bi-image fs-1 text-muted" aria-hidden="true"></i>
            </div>
        @endif
        <!-- Product details-->
        <div class="card-body p-4">
            <div class="text-center">
                <div class="small text-muted mb-1">{{ $product->category->name }}</div>
                <!-- Product name-->
                <h3 class="h5 fw-bolder">{{ $product->name }}</h3>
                <div class="small text-muted mb-2">{{ $product->origin }}</div>
                @foreach ($product->validCertificationTypes() as $type)
                    <span class="badge bg-success"><i class="bi-patch-check me-1" aria-hidden="true"></i>{{ $type->label() }}</span>
                @endforeach
                @if ($showcase?->trust_score !== null)
                    <div class="small mt-2"><i class="bi-shield-check me-1" aria-hidden="true"></i>{{ __('public.catalog.trust_badge', ['score' => $showcase->trust_score]) }}</div>
                @endif
            </div>
        </div>
        <!-- Product actions-->
        <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
            <div class="text-center">
                <a class="btn btn-outline-dark mt-auto stretched-link" href="{{ route('catalog.show', $product) }}">{{ __('public.catalog.view') }}</a>
            </div>
        </div>
    </div>
</div>
