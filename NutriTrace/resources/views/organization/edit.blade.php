@extends('layouts.layout')

@section('title', __('account.organization.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('account.organization.title') }}</h1>
        @if ($organization->is_verified)
            <span class="badge badge-success p-2"><i class="fas fa-check-circle mr-1"></i>{{ __('account.organization.verified') }}</span>
        @else
            <span class="badge badge-secondary p-2" title="{{ __('account.organization.verified_help') }}">
                <i class="fas fa-question-circle mr-1"></i>{{ __('account.organization.not_verified') }}</span>
        @endif
    </div>

    <form method="POST" action="{{ route('organization.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PATCH')

        <div class="row">

            <div class="col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('account.organization.information') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="name">{{ __('account.fields.organization_name') }}</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $organization->name) }}"
                                    class="form-control @error('name') is-invalid @enderror" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label for="registration_number">{{ __('account.fields.registration_number') }}</label>
                                <input type="text" name="registration_number" id="registration_number"
                                    value="{{ old('registration_number', $organization->registration_number) }}"
                                    class="form-control @error('registration_number') is-invalid @enderror">
                                @error('registration_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">{{ __('account.fields.description') }}</label>
                            <textarea name="description" id="description" rows="5"
                                class="form-control @error('description') is-invalid @enderror">{{ old('description', $organization->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex align-items-center">
                            @if ($organization->logoUrl())
                                <img class="rounded avatar-preview mr-3" alt="" src="{{ $organization->logoUrl() }}">
                            @endif
                            <div class="flex-grow-1">
                                <label for="logo">{{ __('account.fields.logo') }}</label>
                                <input type="file" name="logo" id="logo" accept="image/jpeg,image/png,image/webp"
                                    class="form-control-file @error('logo') is-invalid @enderror"
                                    aria-describedby="logoHelp">
                                <small id="logoHelp" class="form-text text-muted">{{ __('account.fields.image_help') }}</small>
                                @error('logo')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('account.organization.location') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="address">{{ __('account.fields.address') }}</label>
                            <input type="text" name="address" id="address"
                                value="{{ old('address', $organization->address) }}"
                                class="form-control @error('address') is-invalid @enderror">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="city">{{ __('account.fields.city') }}</label>
                            <input type="text" name="city" id="city" value="{{ old('city', $organization->city) }}"
                                class="form-control @error('city') is-invalid @enderror" required>
                            @error('city')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="latitude">{{ __('account.fields.latitude') }}</label>
                                <input type="number" step="any" name="latitude" id="latitude"
                                    value="{{ old('latitude', $organization->latitude) }}"
                                    class="form-control @error('latitude') is-invalid @enderror"
                                    aria-describedby="locationHelp" placeholder="34.7406">
                                @error('latitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-6">
                                <label for="longitude">{{ __('account.fields.longitude') }}</label>
                                <input type="number" step="any" name="longitude" id="longitude"
                                    value="{{ old('longitude', $organization->longitude) }}"
                                    class="form-control @error('longitude') is-invalid @enderror"
                                    aria-describedby="locationHelp" placeholder="10.7603">
                                @error('longitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <small id="locationHelp" class="form-text text-muted">{{ __('account.organization.location_help') }}</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block mb-4">
                    <i class="fas fa-save fa-sm mr-1"></i> {{ __('account.organization.save') }}
                </button>
            </div>

        </div>
    </form>

@endsection
