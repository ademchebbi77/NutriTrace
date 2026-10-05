@extends('layouts.layout')

@section('title', __('ui.dashboard.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('account.dashboard.welcome', ['name' => $user->name]) }}</h1>
        <a href="{{ route('producteur.productions.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> {{ __('productions.add') }}</a>
    </div>

    @if ($user->organization && ! $user->organization->hasCoordinates())
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-map-marker-alt mr-1"></i> {{ __('account.dashboard.complete_organization') }}
            <a href="{{ route('organization.edit') }}" class="alert-link">{{ __('account.organization.title') }}</a>
        </div>
    @endif

    <!-- Content Row -->
    <div class="row">
        @foreach ([
            ['key' => 'products', 'icon' => 'fa-apple-alt', 'color' => 'primary', 'route' => 'producteur.products.index'],
            ['key' => 'productions', 'icon' => 'fa-tractor', 'color' => 'success', 'route' => 'producteur.productions.index'],
            ['key' => 'lots_held', 'icon' => 'fa-boxes', 'color' => 'info', 'route' => 'producteur.lots.index'],
            ['key' => 'lots_transferred', 'icon' => 'fa-exchange-alt', 'color' => 'warning', 'route' => 'producteur.lots.index'],
        ] as $card)
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-{{ $card['color'] }} shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-{{ $card['color'] }} text-uppercase mb-1">
                                    <a href="{{ route($card['route']) }}" class="text-{{ $card['color'] }} stretched-link">{{ __('lots.dashboard.'.$card['key']) }}</a>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats[$card['key']] }}</div>
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

        <!-- Area Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('lots.dashboard.per_month') }}</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="productionsPerMonthChart" role="img" aria-label="{{ __('lots.dashboard.per_month') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('lots.dashboard.by_status') }}</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="lotsByStatusChart" role="img" aria-label="{{ __('lots.dashboard.by_status') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('lots.dashboard.latest') }}</h6>
        </div>
        <div class="card-body">
            @if ($latestProductions->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('lots.dashboard.no_data') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('productions.fields.production_date') }}</th>
                                <th>{{ __('productions.fields.product') }}</th>
                                <th>{{ __('productions.fields.quantity') }}</th>
                                <th>{{ __('productions.fields.lot') }}</th>
                                <th>{{ __('lots.fields.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latestProductions as $production)
                                <tr>
                                    <td>{{ $production->production_date->format('d/m/Y') }}</td>
                                    <td>{{ $production->product->name }}</td>
                                    <td>{{ format_quantity($production->quantity, $production->unit) }}</td>
                                    <td><a href="{{ route('producteur.lots.show', $production->lot) }}">{{ $production->lot->lot_number }}</a></td>
                                    <td><span class="badge badge-{{ $production->lot->status->color() }}">{{ $production->lot->status->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

@endsection

@push('scripts')
    <script type="module">
        const charts = @json($charts);

        new Chart(document.getElementById('productionsPerMonthChart'), {
            type: 'bar',
            data: {
                labels: charts.perMonth.labels,
                datasets: [{
                    label: @json(__('lots.dashboard.productions')),
                    data: charts.perMonth.values,
                    backgroundColor: '#2aa63e',
                    hoverBackgroundColor: '#1b7f31',
                    maxBarThickness: 25,
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgb(234, 236, 244)' } },
                },
            },
        });

        new Chart(document.getElementById('lotsByStatusChart'), {
            type: 'doughnut',
            data: {
                labels: charts.byStatus.labels,
                datasets: [{
                    data: charts.byStatus.values,
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
