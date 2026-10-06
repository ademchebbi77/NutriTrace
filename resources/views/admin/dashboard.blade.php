@extends('layouts.layout')

@section('title', __('account.admin.dashboard_title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('account.admin.dashboard_title') }}</h1>
    </div>

    <!-- Content Row: what is waiting for a decision -->
    <div class="row">
        @foreach ([
            ['label' => __('account.admin.pending_accounts'), 'value' => $pendingAccounts, 'icon' => 'fa-user-clock', 'color' => 'warning', 'url' => route('admin.approvals.index')],
            ['label' => __('admin.dashboard.pending_certifications'), 'value' => $pendingCertifications, 'icon' => 'fa-certificate', 'color' => 'info', 'url' => route('admin.certifications.index')],
            ['label' => __('admin.dashboard.open_reports'), 'value' => $openReports, 'icon' => 'fa-flag', 'color' => 'danger', 'url' => route('admin.reports.index')],
            ['label' => __('admin.dashboard.lots'), 'value' => $totalLots, 'icon' => 'fa-boxes', 'color' => 'primary', 'url' => route('admin.lots.index')],
            ['label' => __('account.admin.total_users'), 'value' => $totalUsers, 'icon' => 'fa-users', 'color' => 'secondary', 'url' => route('admin.users.index')],
            ['label' => __('admin.dashboard.average_trust'), 'value' => $averageTrust.' / 100', 'icon' => 'fa-shield-alt', 'color' => 'success', 'url' => route('admin.lots.index')],
        ] as $stat)
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-{{ $stat['color'] }} shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-uppercase mb-1">
                                    <a href="{{ $stat['url'] }}" class="text-{{ $stat['color'] }} stretched-link">{{ $stat['label'] }}</a></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stat['value'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas {{ $stat['icon'] }} fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Content Row -->
    <div class="row">

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('admin.dashboard.grades') }}</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar">
                        <canvas id="gradesChart" role="img" aria-label="{{ __('admin.dashboard.grades') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('account.admin.users_by_role') }}</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="usersByRoleChart" role="img" aria-label="{{ __('account.admin.users_by_role') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Content Row -->
    <div class="row">

        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('account.admin.latest_pending') }}</h6>
                    <a href="{{ route('admin.approvals.index') }}" class="btn btn-sm btn-primary">{{ __('account.admin.see_all') }}</a>
                </div>
                <div class="card-body">
                    @forelse ($latestPending as $pending)
                        <div class="d-flex align-items-center justify-content-between @if (! $loop->last) border-bottom pb-2 mb-2 @endif">
                            <div>
                                <div class="font-weight-bold text-gray-800">{{ $pending->displayName() }}</div>
                                <div class="small text-gray-600">{{ $pending->name }} &middot; {{ $pending->organization?->city }}</div>
                            </div>
                            <span class="badge badge-primary">{{ $pending->role->label() }}</span>
                        </div>
                    @empty
                        <p class="mb-0 text-gray-600">{{ __('account.admin.no_pending') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('admin.dashboard.latest_reports') }}</h6>
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-primary">{{ __('account.admin.see_all') }}</a>
                </div>
                <div class="card-body">
                    @forelse ($latestReports as $report)
                        <div class="d-flex align-items-center justify-content-between @if (! $loop->last) border-bottom pb-2 mb-2 @endif">
                            <div>
                                <a href="{{ route('admin.reports.show', $report) }}" class="font-weight-bold">{{ $report->targetLabel() }}</a>
                                <div class="small text-gray-600">{{ $report->type->label() }} &middot; {{ $report->created_at->format('d/m/Y') }}</div>
                            </div>
                            <span class="badge badge-{{ $report->status->color() }}">{{ $report->status->label() }}</span>
                        </div>
                    @empty
                        <p class="mb-0 text-gray-600">{{ __('admin.dashboard.no_reports') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

@endsection

@push('scripts')
    <script type="module">
        const roleChart = @json($roleChart);
        const gradeChart = @json($gradeChart);

        new Chart(document.getElementById('usersByRoleChart'), {
            type: 'doughnut',
            data: {
                labels: roleChart.labels,
                datasets: [{
                    data: roleChart.values,
                    backgroundColor: ['#2aa63e', '#8a5a2b', '#f6c23e', '#0f8f7a', '#858796'],
                    hoverBorderColor: 'rgba(234, 236, 244, 1)',
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: { legend: { position: 'bottom' } },
            },
        });

        new Chart(document.getElementById('gradesChart'), {
            type: 'bar',
            data: {
                labels: gradeChart.labels,
                datasets: [{
                    label: @json(__('admin.dashboard.lots')),
                    data: gradeChart.values,
                    backgroundColor: ['#1cc88a', '#0f8f7a', '#f6c23e', '#e74a3b', '#5a5c69'],
                    maxBarThickness: 40,
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    </script>
@endpush
