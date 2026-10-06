@extends('layouts.layout')

@section('title', __('menu.favorites'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('menu.favorites') }}</h1>

    @if ($products->isEmpty())
        <div class="card shadow mb-4">
            <div class="card-body">
                <p class="mb-0 text-gray-600">{{ __('public.favorites.empty') }}
                    <a href="{{ route('catalog.index') }}">{{ __('dashboards.consumer.explore') }}</a></p>
            </div>
        </div>
    @else
        <div class="row">
            @foreach ($products as $product)
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card shadow h-100">
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" class="card-img-top" alt="">
                        @endif
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">{{ $product->category->name }}</div>
                            <a href="{{ route('catalog.show', $product) }}" class="h6 font-weight-bold text-gray-800">{{ $product->name }}</a>
                            <div class="small text-gray-600">{{ $product->origin }}</div>
                        </div>
                        <div class="card-footer bg-white">
                            <form method="POST" action="{{ route('favorites.toggle', $product) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm btn-block">
                                    <i class="fas fa-heart-broken fa-sm"></i> {{ __('public.favorites.remove') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@endsection
