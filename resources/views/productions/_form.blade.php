{{-- Shared by create and edit. Expects $production, $products, $units, $methods. --}}
@php
    $resources = old('resources_used', $production->resources_used ?? []);
    $expiration = old('expiration_date', $production->lot?->expiration_date?->toDateString());
@endphp

@if ($products->isEmpty())
    <div class="alert alert-warning" role="alert">
        {{ __('productions.need_product') }}
        <a href="{{ area_route('products.create') }}" class="alert-link">{{ __('products.add') }}</a>
    </div>
@endif

<div class="row">

    <div class="col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ __('productions.sections.product') }}</h6>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="product_id">{{ __('productions.fields.product') }}</label>
                    <select name="product_id" id="product_id" class="custom-select @error('product_id') is-invalid @enderror" required>
                        <option value="">{{ __('productions.fields.choose_product') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((int) old('product_id', $production->product_id) === $product->id)>
                                {{ $product->name }}</option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="quantity">{{ __('productions.fields.quantity') }}</label>
                        <input type="number" step="0.01" min="0.01" name="quantity" id="quantity"
                            value="{{ old('quantity', $production->quantity) }}"
                            class="form-control @error('quantity') is-invalid @enderror" required>
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label for="unit">{{ __('productions.fields.unit') }}</label>
                        <select name="unit" id="unit" class="custom-select @error('unit') is-invalid @enderror" required>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->value }}" @selected(old('unit', $production->unit?->value) === $unit->value)>
                                    {{ $unit->label() }}</option>
                            @endforeach
                        </select>
                        @error('unit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-4">
                        <label for="production_method">{{ __('productions.fields.production_method') }}</label>
                        <select name="production_method" id="production_method"
                            class="custom-select @error('production_method') is-invalid @enderror" required>
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}" @selected(old('production_method', $production->production_method?->value) === $method->value)>
                                    {{ $method->label() }}</option>
                            @endforeach
                        </select>
                        @error('production_method')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-md-0">
                        <label for="production_date">{{ __('productions.fields.production_date') }}</label>
                        <input type="date" name="production_date" id="production_date" max="{{ today()->toDateString() }}"
                            value="{{ old('production_date', $production->production_date?->toDateString()) }}"
                            class="form-control @error('production_date') is-invalid @enderror" required>
                        @error('production_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6 mb-0">
                        <label for="expiration_date">{{ __('productions.fields.expiration_date') }}</label>
                        <input type="date" name="expiration_date" id="expiration_date" value="{{ $expiration }}"
                            class="form-control @error('expiration_date') is-invalid @enderror">
                        @error('expiration_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ __('productions.sections.resources') }}</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted" id="resourcesHelp">{{ __('productions.fields.resources_help') }}</p>
                <div class="form-row">
                    @foreach (App\Models\Production::RESOURCE_KEYS as $key)
                        <div class="form-group col-md-3 col-6">
                            <label for="resource_{{ $key }}">{{ __('productions.fields.'.$key) }}</label>
                            <input type="number" step="0.01" min="0" name="resources_used[{{ $key }}]" id="resource_{{ $key }}"
                                value="{{ $resources[$key] ?? '' }}" aria-describedby="resourcesHelp"
                                class="form-control @error('resources_used.'.$key) is-invalid @enderror">
                            @error('resources_used.'.$key)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                </div>
                <div class="form-group mb-0">
                    <label for="resource_notes">{{ __('productions.fields.notes') }}</label>
                    <input type="text" name="resources_used[notes]" id="resource_notes" maxlength="500"
                        value="{{ $resources['notes'] ?? '' }}"
                        class="form-control @error('resources_used.notes') is-invalid @enderror">
                    @error('resources_used.notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ __('productions.sections.location') }}</h6>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="location_address">{{ __('productions.fields.location_address') }}</label>
                    <input type="text" name="location_address" id="location_address"
                        value="{{ old('location_address', $production->location_address) }}"
                        class="form-control @error('location_address') is-invalid @enderror">
                    @error('location_address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="location_city">{{ __('productions.fields.location_city') }}</label>
                    <input type="text" name="location_city" id="location_city"
                        value="{{ old('location_city', $production->location_city) }}"
                        class="form-control @error('location_city') is-invalid @enderror" required>
                    @error('location_city')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label for="latitude">{{ __('productions.fields.latitude') }}</label>
                        <input type="number" step="any" name="latitude" id="latitude"
                            value="{{ old('latitude', $production->latitude) }}" aria-describedby="locationHelp"
                            class="form-control @error('latitude') is-invalid @enderror">
                        @error('latitude')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-6">
                        <label for="longitude">{{ __('productions.fields.longitude') }}</label>
                        <input type="number" step="any" name="longitude" id="longitude"
                            value="{{ old('longitude', $production->longitude) }}" aria-describedby="locationHelp"
                            class="form-control @error('longitude') is-invalid @enderror">
                        @error('longitude')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <small id="locationHelp" class="form-text text-muted">{{ __('productions.fields.location_help') }}</small>
            </div>
        </div>

        @unless ($production->exists)
            <div class="alert alert-info" role="note">
                <i class="fas fa-boxes mr-1"></i> {{ __('productions.lot_notice') }}
            </div>
        @endunless

        <button type="submit" class="btn btn-primary btn-block" @disabled($products->isEmpty())>
            <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
        </button>
        <a href="{{ area_route('productions.index') }}" class="btn btn-light btn-block mb-4">{{ __('ui.cancel') }}</a>
    </div>

</div>
