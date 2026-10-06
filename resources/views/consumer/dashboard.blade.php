@extends('layouts.layout')

@section('title', __('ui.dashboard.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('account.dashboard.welcome', ['name' => $user->name]) }}</h1>
        <a href="{{ route('catalog.index') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-search fa-sm text-white-50"></i> {{ __('dashboards.consumer.explore') }}</a>
    </div>

    <!-- Content Row -->
    <div class="row">
        @foreach ([
            ['key' => 'history', 'icon' => 'fa-history', 'color' => 'primary', 'route' => 'consommateur.history.index'],
            ['key' => 'favorites', 'icon' => 'fa-heart', 'color' => 'danger', 'route' => 'consommateur.favorites.index'],
            ['key' => 'reviews', 'icon' => 'fa-star', 'color' => 'warning', 'route' => 'consommateur.reviews.index'],
            ['key' => 'reports', 'icon' => 'fa-flag', 'color' => 'info', 'route' => 'consommateur.reports.index'],
        ] as $card)
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-{{ $card['color'] }} shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-uppercase mb-1">
                                    <a href="{{ route($card['route']) }}" class="text-{{ $card['color'] }} stretched-link">{{ __('dashboards.consumer.'.$card['key']) }}</a></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts[$card['key']] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas {{ $card['icon'] }} fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('dashboards.consumer.recent') }}</h6>
        </div>
        <div class="card-body">
            @forelse ($recent as $lot)
                <div class="d-flex align-items-center justify-content-between @if (! $loop->last) border-bottom pb-2 mb-2 @endif">
                    <div>
                        <a href="{{ $lot->publicUrl() }}" class="font-weight-bold">{{ $lot->product->name }}</a>
                        <div class="small text-gray-600">{{ $lot->lot_number }} &middot; {{ \Illuminate\Support\Carbon::parse($lot->pivot->viewed_at)->format('d/m/Y H:i') }}</div>
                    </div>
                    @if ($lot->environmentalImpact?->grade)
                        <span class="badge badge-{{ $lot->environmentalImpact->gradeColor() }} px-2 py-1">{{ $lot->environmentalImpact->grade }}</span>
                    @endif
                </div>
            @empty
                <p class="mb-0 text-gray-600">{{ __('dashboards.consumer.recent_empty') }}</p>
            @endforelse
        </div>
    </div>

@endsection
