@extends('layouts.layout')

@section('title', __('lots.edit_title', ['number' => $lot->lot_number]))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('lots.edit_title', ['number' => $lot->lot_number]) }}</h1>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ $lot->product->name }} &middot; {{ $lot->formattedQuantity() }}</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ area_route('lots.update', $lot) }}" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="production_date">{{ __('lots.fields.production_date') }}</label>
                            <input type="text" id="production_date" class="form-control" readonly
                                value="{{ $lot->production_date->format('d/m/Y') }}">
                        </div>
                        <div class="form-group">
                            <label for="expiration_date">{{ __('lots.fields.expiration_date') }}</label>
                            <input type="date" name="expiration_date" id="expiration_date"
                                value="{{ old('expiration_date', $lot->expiration_date?->toDateString()) }}"
                                class="form-control @error('expiration_date') is-invalid @enderror">
                            @error('expiration_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
                        </button>
                        <a href="{{ area_route('lots.show', $lot) }}" class="btn btn-light">{{ __('ui.cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
