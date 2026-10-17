@extends('layouts.layout')

@section('title', __('transfers.transports.edit_title', ['number' => $transport->lot->lot_number]))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('transfers.transports.edit_title', ['number' => $transport->lot->lot_number]) }}</h1>

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.transports.correct') }}</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted" id="correctHelp">{{ __('transfers.transports.correct_help') }}</p>
                    <form method="POST" action="{{ area_route('transports.update', $transport) }}" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="form-row">
                            <div class="form-group col-md-6">
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
                            <div class="form-group col-md-6">
                                <label for="distance_km">{{ __('transfers.fields.distance_km') }}</label>
                                <input type="number" step="0.1" min="0" name="distance_km" id="distance_km"
                                    value="{{ old('distance_km', $transport->distance_km) }}" aria-describedby="correctHelp"
                                    class="form-control @error('distance_km') is-invalid @enderror" required>
                                @error('distance_km')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
                        </button>
                        <a href="{{ area_route('transports.show', $transport) }}" class="btn btn-light">{{ __('ui.cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-left-primary shadow mb-4">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">{{ __('transfers.fields.lot') }}</div>
                    <div class="h5 mb-1 font-weight-bold text-gray-800">{{ $transport->lot->lot_number }}</div>
                    <div class="text-gray-700">{{ $transport->lot->product->name }}</div>
                    <div class="small text-gray-600 mt-2">{{ $transport->origin_label }} &rarr; {{ $transport->destination_label }}</div>
                </div>
            </div>
        </div>

    </div>

@endsection
