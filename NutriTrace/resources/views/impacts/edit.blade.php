@extends('layouts.layout')

@section('title', __('impacts.edit_title', ['number' => $lot->lot_number]))

@php
    $declared = old('declared', $impact->declared ?? []);
    $stageValues = [
        'production' => $stages['production_co2'],
        'transformation' => $stages['transformation_co2'],
        'transport' => $stages['transport_co2'],
        'packaging' => $impact->packaging_co2_kg,
    ];
    $hasStages = collect($stageValues)->filter()->isNotEmpty();
@endphp

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ __('impacts.edit_title', ['number' => $lot->lot_number]) }}
            <small class="text-gray-600">{{ $lot->product->name }}</small>
        </h1>
        <a href="{{ area_route('lots.show', $lot) }}" class="btn btn-sm btn-light shadow-sm">
            <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
    </div>

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('impacts.result_card') }}</h6>
                    @if ($impact->grade)
                        <span class="badge badge-{{ $impact->gradeColor() }} px-3 py-2">{{ __('impacts.fields.grade') }} {{ $impact->grade }} &middot; {{ $impact->score }} / 100</span>
                    @else
                        <span class="badge badge-secondary px-3 py-2">{{ __('impacts.not_available') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>{{ __('impacts.fields.indicator') }}</th>
                                    <th>{{ __('impacts.fields.current') }}</th>
                                    <th>{{ __('impacts.fields.source') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (App\Models\EnvironmentalImpact::INDICATORS as $indicator)
                                    <tr>
                                        <td>{{ __('impacts.indicators.'.$indicator) }}</td>
                                        <td>
                                            @if ($impact->{$indicator} !== null)
                                                {{ number_format($impact->{$indicator}, 2, ',', ' ') }} {{ __('impacts.units.'.$indicator) }}
                                            @else
                                                <span class="text-gray-500">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($impact->source($indicator))
                                                <span class="badge badge-{{ $impact->source($indicator)->color() }}"
                                                    title="{{ __('impacts.source_help.'.$impact->source($indicator)->value) }}">{{ $impact->source($indicator)->label() }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td>{{ __('impacts.indicators.food_miles_km') }}</td>
                                    <td>{{ number_format($impact->food_miles_km ?? 0, 0, ',', ' ') }} km</td>
                                    <td><span class="badge badge-secondary">{{ App\Enums\DataSource::CALCULATED->label() }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <ul class="small text-muted mt-3 mb-0 pl-3">
                        @foreach (App\Enums\DataSource::cases() as $source)
                            <li>{{ __('impacts.source_help.'.$source->value) }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('impacts.stages_card') }}</h6>
                </div>
                <div class="card-body">
                    @if ($hasStages)
                        <div class="chart-bar">
                            <canvas id="stagesChart" role="img" aria-label="{{ __('impacts.stages_card') }}"></canvas>
                        </div>
                    @else
                        <p class="mb-0 text-gray-600">{{ __('impacts.no_stage_data') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('impacts.declared_card') }}</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted" id="declaredHelp">{{ __('impacts.declared_help') }}</p>
                    <form method="POST" action="{{ area_route('impacts.update', $lot) }}" novalidate>
                        @csrf
                        @method('PUT')
                        @foreach ($declarable as $indicator)
                            <div class="form-row">
                                <div class="form-group col-7">
                                    <label for="value_{{ $indicator }}">{{ __('impacts.indicators.'.$indicator) }} ({{ __('impacts.units.'.$indicator) }})</label>
                                    <input type="number" step="0.001" min="0" name="declared[{{ $indicator }}][value]" id="value_{{ $indicator }}"
                                        value="{{ $declared[$indicator]['value'] ?? '' }}" aria-describedby="declaredHelp"
                                        class="form-control @error('declared.'.$indicator.'.value') is-invalid @enderror">
                                    @error('declared.'.$indicator.'.value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-5">
                                    <label for="source_{{ $indicator }}">{{ __('impacts.fields.source') }}</label>
                                    <select name="declared[{{ $indicator }}][source]" id="source_{{ $indicator }}"
                                        class="custom-select @error('declared.'.$indicator.'.source') is-invalid @enderror">
                                        <option value="">{{ __('impacts.fields.choose_source') }}</option>
                                        @foreach ($sources as $source)
                                            <option value="{{ $source->value }}" @selected(($declared[$indicator]['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('declared.'.$indicator.'.source')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        @endforeach
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

@endsection

@if ($hasStages)
    @push('scripts')
        <script type="module">
            const stages = @json(collect($stageValues)->map(fn ($value) => round($value ?? 0, 2)));
            const labels = @json(__('impacts.stages'));

            new Chart(document.getElementById('stagesChart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(stages).map((key) => labels[key]),
                    datasets: [{
                        label: 'kg CO₂e',
                        data: Object.values(stages),
                        backgroundColor: ['#1cc88a', '#f6c23e', '#0f8f7a', '#858796'],
                        maxBarThickness: 40,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, title: { display: true, text: 'kg CO₂e' } },
                    },
                },
            });
        </script>
    @endpush
@endif
