@extends('layouts.layout')

@section('title', __('transfers.send_title', ['number' => $lot->lot_number]))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('transfers.send_title', ['number' => $lot->lot_number]) }}</h1>

    <div class="alert alert-info" role="note">
        <i class="fas fa-info-circle mr-1"></i> {{ __('transfers.how_it_works') }}
    </div>

    <form method="POST" action="{{ area_route('transfers.store', $lot) }}" novalidate>
        @csrf

        <div class="row">

            <div class="col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.sections.recipient') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="recipient_id">{{ __('transfers.fields.recipient') }}</label>
                            <select name="recipient_id" id="recipient_id" class="custom-select @error('recipient_id') is-invalid @enderror" required>
                                <option value="">{{ __('transfers.fields.choose_recipient') }}</option>
                                @foreach ($recipients as $role => $users)
                                    <optgroup label="{{ $role }}">
                                        @foreach ($users as $recipient)
                                            <option value="{{ $recipient->id }}" @selected((int) old('recipient_id') === $recipient->id)>
                                                {{ $recipient->displayName() }}@if ($recipient->organization?->city) ({{ $recipient->organization->city }})@endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('recipient_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group mb-0">
                            <label for="note">{{ __('transfers.fields.note') }}</label>
                            <textarea name="note" id="note" rows="2" maxlength="500"
                                class="form-control @error('note') is-invalid @enderror">{{ old('note') }}</textarea>
                            @error('note')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.sections.transport') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="transport_type">{{ __('transfers.fields.transport_type') }}</label>
                                <select name="transport_type" id="transport_type" class="custom-select @error('transport_type') is-invalid @enderror" required>
                                    @foreach ($transportTypes as $type)
                                        <option value="{{ $type->value }}" @selected(old('transport_type', 'truck') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                @error('transport_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-5">
                                <label for="departure_date">{{ __('transfers.fields.departure_date') }}</label>
                                <input type="datetime-local" name="departure_date" id="departure_date"
                                    value="{{ old('departure_date', now()->format('Y-m-d\TH:i')) }}"
                                    min="{{ $lot->production_date->format('Y-m-d\T00:00') }}" max="{{ now()->format('Y-m-d\TH:i') }}"
                                    class="form-control @error('departure_date') is-invalid @enderror" required>
                                @error('departure_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label for="distance_km">{{ __('transfers.fields.distance_km') }}</label>
                                <input type="number" step="0.1" min="0" name="distance_km" id="distance_km" value="{{ old('distance_km') }}"
                                    class="form-control @error('distance_km') is-invalid @enderror" aria-describedby="distanceHelp">
                                @error('distance_km')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <small id="distanceHelp" class="form-text text-muted">{{ __('transfers.fields.distance_help') }}</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-left-primary shadow mb-4">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">{{ __('transfers.sections.lot') }}</div>
                        <div class="h5 mb-1 font-weight-bold text-gray-800">{{ $lot->lot_number }}</div>
                        <div class="text-gray-700">{{ $lot->product->name }}</div>
                        <div class="h4 mt-2 mb-0 text-gray-800">{{ $lot->formattedQuantity() }}</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane fa-sm mr-1"></i> {{ __('transfers.send_button') }}
                </button>
                <a href="{{ area_route('lots.show', $lot) }}" class="btn btn-light btn-block mb-4">{{ __('ui.cancel') }}</a>
            </div>

        </div>
    </form>

@endsection
