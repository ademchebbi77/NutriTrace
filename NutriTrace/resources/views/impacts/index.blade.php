@extends('layouts.layout')

@section('title', __('impacts.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('impacts.title') }}</h1>

    <p class="text-gray-600"><i class="fas fa-info-circle mr-1"></i>{{ __('impacts.intro') }}</p>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('impacts.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($lots->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('impacts.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('lots.fields.lot_number') }}</th>
                                <th>{{ __('lots.fields.product') }}</th>
                                <th>{{ __('impacts.indicators.co2_kg') }}</th>
                                <th>{{ __('impacts.indicators.water_l') }}</th>
                                <th>{{ __('impacts.indicators.energy_kwh') }}</th>
                                <th>{{ __('impacts.indicators.food_miles_km') }}</th>
                                <th>{{ __('impacts.fields.grade') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $lot)
                                @php $impact = $lot->environmentalImpact; @endphp
                                <tr>
                                    <td><a href="{{ area_route('lots.show', $lot) }}" class="font-weight-bold">{{ $lot->lot_number }}</a></td>
                                    <td>{{ $lot->product->name }}</td>
                                    @foreach (['co2_kg', 'water_l', 'energy_kwh'] as $indicator)
                                        <td data-order="{{ $impact?->{$indicator} ?? -1 }}">
                                            @if ($impact?->{$indicator} !== null)
                                                {{ number_format($impact->{$indicator}, 1, ',', ' ') }} {{ __('impacts.units.'.$indicator) }}
                                                <br><span class="badge badge-{{ $impact->source($indicator)->color() }}">{{ $impact->source($indicator)->label() }}</span>
                                            @else
                                                <span class="text-gray-500">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td data-order="{{ $impact?->food_miles_km ?? 0 }}">{{ number_format($impact?->food_miles_km ?? 0, 0, ',', ' ') }} km</td>
                                    <td data-order="{{ $impact?->score ?? -1 }}">
                                        @if ($impact?->grade)
                                            <span class="badge badge-{{ $impact->gradeColor() }} px-2 py-1">{{ $impact->grade }}</span>
                                            <span class="small text-gray-600">{{ $impact->score }} / 100</span>
                                        @else
                                            <span class="text-gray-500">{{ __('impacts.not_available') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @can('declareImpact', $lot)
                                            <a href="{{ area_route('impacts.edit', $lot) }}" class="btn btn-primary btn-sm">
                                                <i class="fas fa-leaf fa-sm"></i> {{ __('impacts.declare') }}</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

@endsection
