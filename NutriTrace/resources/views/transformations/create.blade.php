@extends('layouts.layout')

@section('title', __('transformations.create_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('transformations.create_title') }}</h1>

    @if ($lots->isEmpty())
        <div class="alert alert-warning" role="alert">
            {{ __('transformations.no_lots') }}
            <a href="{{ area_route('receptions.index') }}" class="alert-link">{{ __('transfers.receptions.title') }}</a>
        </div>
    @endif

    @if ($products->isEmpty())
        <div class="alert alert-warning" role="alert">
            {{ __('transformations.need_product') }}
            <a href="{{ area_route('products.create') }}" class="alert-link">{{ __('products.add') }}</a>
        </div>
    @endif

    <form method="POST" action="{{ area_route('transformations.store') }}" novalidate>
        @csrf

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ __('transformations.sections.inputs') }}</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted" id="inputsHelp">{{ __('transformations.fields.inputs_help') }}</p>
                @error('inputs')
                    <div class="alert alert-danger" role="alert">{{ $message }}</div>
                @enderror
                @if ($lots->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>{{ __('lots.fields.lot_number') }}</th>
                                    <th>{{ __('lots.fields.product') }}</th>
                                    <th>{{ __('lots.fields.production_date') }}</th>
                                    <th>{{ __('transformations.fields.available') }}</th>
                                    <th style="width: 14rem;">{{ __('transformations.fields.quantity_used') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lots as $lot)
                                    <tr>
                                        <td class="font-weight-bold"><label for="quantity_{{ $lot->id }}" class="mb-0">{{ $lot->lot_number }}</label></td>
                                        <td>{{ $lot->product->name }}</td>
                                        <td>{{ $lot->production_date->format('d/m/Y') }}</td>
                                        <td>{{ $lot->formattedQuantity() }}</td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="{{ $lot->quantity }}"
                                                    name="quantities[{{ $lot->id }}]" id="quantity_{{ $lot->id }}"
                                                    value="{{ old('quantities.'.$lot->id) }}" aria-describedby="inputsHelp"
                                                    class="form-control @error('quantities.'.$lot->id) is-invalid @enderror">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">{{ $lot->unit->symbol() }}</span>
                                                </div>
                                                @error('quantities.'.$lot->id)
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="row">

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('transformations.sections.output') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="output_product_id">{{ __('transformations.fields.output_product') }}</label>
                            <select name="output_product_id" id="output_product_id" class="custom-select @error('output_product_id') is-invalid @enderror" required>
                                <option value="">{{ __('transformations.fields.choose_product') }}</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected((int) old('output_product_id') === $product->id)>{{ $product->name }}</option>
                                @endforeach
                            </select>
                            @error('output_product_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-7">
                                <label for="output_quantity">{{ __('transformations.fields.output_quantity') }}</label>
                                <input type="number" step="0.01" min="0.01" name="output_quantity" id="output_quantity" value="{{ old('output_quantity') }}"
                                    class="form-control @error('output_quantity') is-invalid @enderror" required>
                                @error('output_quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-5">
                                <label for="output_unit">{{ __('transformations.fields.output_unit') }}</label>
                                <select name="output_unit" id="output_unit" class="custom-select @error('output_unit') is-invalid @enderror" required>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->value }}" @selected(old('output_unit', 'kg') === $unit->value)>{{ $unit->label() }}</option>
                                    @endforeach
                                </select>
                                @error('output_unit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-md-0">
                                <label for="transformation_date">{{ __('transformations.fields.transformation_date') }}</label>
                                <input type="date" name="transformation_date" id="transformation_date" max="{{ today()->toDateString() }}"
                                    value="{{ old('transformation_date', today()->toDateString()) }}"
                                    class="form-control @error('transformation_date') is-invalid @enderror" required>
                                @error('transformation_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6 mb-0">
                                <label for="expiration_date">{{ __('transformations.fields.expiration_date') }}</label>
                                <input type="date" name="expiration_date" id="expiration_date" value="{{ old('expiration_date') }}"
                                    class="form-control @error('expiration_date') is-invalid @enderror">
                                @error('expiration_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('transformations.sections.process') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="process_description">{{ __('transformations.fields.process_description') }}</label>
                            <textarea name="process_description" id="process_description" rows="4" aria-describedby="processHelp"
                                class="form-control @error('process_description') is-invalid @enderror" required>{{ old('process_description') }}</textarea>
                            <small id="processHelp" class="form-text text-muted">{{ __('transformations.fields.process_help') }}</small>
                            @error('process_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="energy_used_kwh">{{ __('transformations.fields.energy_used_kwh') }}</label>
                                <input type="number" step="0.01" min="0" name="energy_used_kwh" id="energy_used_kwh" value="{{ old('energy_used_kwh') }}"
                                    aria-describedby="resourcesHelp" class="form-control @error('energy_used_kwh') is-invalid @enderror">
                                @error('energy_used_kwh')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-6">
                                <label for="water_used_l">{{ __('transformations.fields.water_used_l') }}</label>
                                <input type="number" step="0.01" min="0" name="water_used_l" id="water_used_l" value="{{ old('water_used_l') }}"
                                    aria-describedby="resourcesHelp" class="form-control @error('water_used_l') is-invalid @enderror">
                                @error('water_used_l')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <small id="resourcesHelp" class="form-text text-muted">{{ __('transformations.fields.resources_help') }}</small>
                    </div>
                </div>

                <div class="alert alert-warning" role="note">
                    <i class="fas fa-exclamation-triangle mr-1"></i> {{ __('transformations.final_notice') }}
                </div>

                <button type="submit" class="btn btn-primary btn-block" @disabled($lots->isEmpty() || $products->isEmpty())>
                    <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
                </button>
                <a href="{{ area_route('transformations.index') }}" class="btn btn-light btn-block mb-4">{{ __('ui.cancel') }}</a>
            </div>

        </div>
    </form>

@endsection
