@extends('layouts.layout')

@section('title', __('transfers.transports.show_title', ['number' => $transport->lot->lot_number]))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ __('transfers.transports.show_title', ['number' => $transport->lot->lot_number]) }}
            <span class="badge badge-{{ $transport->status->color() }} align-middle">{{ $transport->status->label() }}</span>
        </h1>
        <a href="{{ area_route('transports.index') }}" class="btn btn-sm btn-light shadow-sm">
            <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
    </div>

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.transports.route') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('transfers.fields.lot') }}</dt>
                        <dd class="col-sm-8">
                            @can('view', $transport->lot)
                                <a href="{{ area_route('lots.show', $transport->lot) }}">{{ $transport->lot->lot_number }}</a>
                            @else
                                {{ $transport->lot->lot_number }}
                            @endcan
                            &middot; {{ $transport->lot->product->name }}
                        </dd>

                        <dt class="col-sm-4">{{ __('transfers.fields.quantity') }}</dt>
                        <dd class="col-sm-8">{{ format_quantity($transport->quantity_transported, $transport->unit) }}</dd>

                        <dt class="col-sm-4">{{ __('transfers.fields.origin') }}</dt>
                        <dd class="col-sm-8">{{ $transport->origin_label }}<br>
                            <span class="small text-gray-600">{{ $transport->departure_date->format('d/m/Y H:i') }}</span></dd>

                        <dt class="col-sm-4">{{ __('transfers.fields.destination') }}</dt>
                        <dd class="col-sm-8">{{ $transport->destination_label }}<br>
                            <span class="small text-gray-600">{{ $transport->arrival_date?->format('d/m/Y H:i') ?? $transport->status->label() }}</span></dd>

                        <dt class="col-sm-4">{{ __('transfers.fields.transport_type') }}</dt>
                        <dd class="col-sm-8"><i class="fas {{ $transport->transport_type->icon() }} fa-sm"></i> {{ $transport->transport_type->label() }}</dd>

                        <dt class="col-sm-4">{{ __('transfers.fields.distance_km') }}</dt>
                        <dd class="col-sm-8">{{ $transport->distance_km !== null ? number_format($transport->distance_km, 1, ',', ' ').' km' : __('transfers.transports.no_distance') }}</dd>

                        <dt class="col-sm-4">{{ __('transfers.fields.co2') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $co2 !== null ? number_format($co2, 2, ',', ' ').' kg CO₂e' : '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        @can('update', $transport)
            <div class="col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.transports.correct') }}</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted" id="correctHelp">{{ __('transfers.transports.correct_help') }}</p>
                        <form method="POST" action="{{ area_route('transports.update', $transport) }}" novalidate>
                            @csrf
                            @method('PUT')
                            <div class="form-group">
                                <label for="transport_type">{{ __('transfers.fields.transport_type') }}</label>
                                <select name="transport_type" id="transport_type" class="custom-select @error('transport_type') is-invalid @enderror" required>
                                    @foreach ($transportTypes as $type)
                                        <option value="{{ $type->value }}" @selected(old('transport_type', $transport->transport_type->value) === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('transport_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="distance_km">{{ __('transfers.fields.distance_km') }}</label>
                                <input type="number" step="0.1" min="0" name="distance_km" id="distance_km"
                                    value="{{ old('distance_km', $transport->distance_km) }}" aria-describedby="correctHelp"
                                    class="form-control @error('distance_km') is-invalid @enderror" required>
                                @error('distance_km')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan

    </div>

@endsection
