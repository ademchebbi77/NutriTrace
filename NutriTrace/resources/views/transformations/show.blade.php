@extends('layouts.layout')

@section('title', __('transformations.show_title', ['date' => $transformation->transformation_date->format('d/m/Y')]))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ $transformation->outputLot->product->name }}
            <small class="text-gray-600">{{ $transformation->transformation_date->format('d/m/Y') }}</small>
        </h1>
        <a href="{{ area_route('transformations.index') }}" class="btn btn-sm btn-light shadow-sm">
            <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
    </div>

    <div class="row">

        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('transformations.sections.inputs') }}</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>{{ __('lots.fields.lot_number') }}</th>
                                    <th>{{ __('lots.fields.product') }}</th>
                                    <th>{{ __('transformations.fields.origin') }}</th>
                                    <th>{{ __('transformations.fields.quantity_used') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transformation->inputs as $input)
                                    <tr>
                                        <td><a href="{{ area_route('lots.show', $input->lot) }}" class="font-weight-bold">{{ $input->lot->lot_number }}</a></td>
                                        <td>{{ $input->lot->product->name }}</td>
                                        <td>
                                            @if ($input->lot->production)
                                                {{ $input->lot->production->producer->displayName() }}, {{ $input->lot->production->location_city }}
                                            @else
                                                {{ __('lots.from_transformation') }}
                                            @endif
                                        </td>
                                        <td>{{ format_quantity($input->quantity_used, $input->lot->unit) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('transformations.sections.process') }}</h6>
                </div>
                <div class="card-body">
                    <p>{!! nl2br(e($transformation->process_description)) !!}</p>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('transformations.fields.transformer') }}</dt>
                        <dd class="col-sm-8">{{ $transformation->location_label }}</dd>

                        <dt class="col-sm-4">{{ __('transformations.fields.energy_used_kwh') }}</dt>
                        <dd class="col-sm-8">{{ $transformation->energy_used_kwh !== null ? number_format($transformation->energy_used_kwh, 2, ',', ' ') : __('transformations.fields.not_declared') }}</dd>

                        <dt class="col-sm-4">{{ __('transformations.fields.water_used_l') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $transformation->water_used_l !== null ? number_format($transformation->water_used_l, 2, ',', ' ') : __('transformations.fields.not_declared') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-left-success shadow mb-4">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">{{ __('transformations.sections.output_lot') }}</div>
                    <div class="h5 mb-1 font-weight-bold text-gray-800">{{ $transformation->outputLot->lot_number }}</div>
                    <div class="text-gray-700">{{ $transformation->outputLot->product->name }}</div>
                    <div class="h4 mt-2 mb-0 text-gray-800">{{ format_quantity($transformation->output_quantity, $transformation->outputLot->unit) }}</div>
                    @if ($transformation->yieldPercent() !== null)
                        <div class="small text-gray-600 mt-1">{{ __('transformations.fields.yield') }} :
                            {{ number_format($transformation->yieldPercent(), 1, ',', ' ') }} %</div>
                    @endif
                    <a href="{{ area_route('lots.show', $transformation->outputLot) }}" class="btn btn-success btn-sm mt-3">
                        <i class="fas fa-boxes fa-sm mr-1"></i> {{ __('ui.view') }}</a>
                </div>
            </div>
        </div>

    </div>

@endsection
