@extends('layouts.layout')

@section('title', __('ui.dashboard.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('account.dashboard.welcome', ['name' => $user->name]) }}</h1>
    </div>

    @if ($user->organization && ! $user->organization->hasCoordinates())
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-map-marker-alt mr-1"></i> {{ __('account.dashboard.complete_organization') }}
            <a href="{{ route('organization.edit') }}" class="alert-link">{{ __('account.organization.title') }}</a>
        </div>
    @endif

    <!-- Content Row -->
    <div class="row">
        @foreach ($cards as $card)
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-{{ $card['color'] }} shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-uppercase mb-1">
                                    <a href="{{ $card['url'] }}" class="text-{{ $card['color'] }} stretched-link">{{ $card['label'] }}</a>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $card['value'] }}</div>
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

    <!-- Content Row -->
    <div class="row">

        <div class="col-xl-7 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('dashboards.recent_activity') }}</h6>
                </div>
                <div class="card-body">
                    @forelse ($events as $event)
                        <div class="d-flex @if (! $loop->last) border-bottom pb-2 mb-2 @endif">
                            <span class="btn btn-{{ $event->event_type->color() }} btn-circle btn-sm mr-3" aria-hidden="true">
                                <i class="fas {{ $event->event_type->icon() }}"></i></span>
                            <div>
                                <div><span class="font-weight-bold">{{ $event->lot->lot_number }}</span> &middot; {{ $event->description }}</div>
                                <div class="small text-gray-600">{{ $event->occurred_at->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="mb-0 text-gray-600">{{ __('account.dashboard.intro.'.$user->role->value) }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-5 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('dashboards.lots_by_status') }}</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="lotsByStatusChart" role="img" aria-label="{{ __('dashboards.lots_by_status') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

@endsection

@push('scripts')
    <script type="module">
        const statusChart = @json($statusChart);

        new Chart(document.getElementById('lotsByStatusChart'), {
            type: 'doughnut',
            data: {
                labels: statusChart.labels,
                datasets: [{
                    data: statusChart.values,
                    backgroundColor: ['#858796', '#f6c23e', '#0f8f7a', '#2aa63e', '#1cc88a', '#5a5c69'],
                    hoverBorderColor: 'rgba(234, 236, 244, 1)',
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'bottom' } },
            },
        });
    </script>
@endpush
