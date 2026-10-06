@extends('layouts.layout')

@section('title', __('admin.settings.title'))

@php
    // One number input bound to a configuration key, e.g. "footprint.emission_factors.transport.truck".
    $field = function (string $key, string $label, string $step = 'any') {
        $name = collect(explode('.', $key))->map(fn ($part, $index) => $index === 0 ? $part : "[$part]")->implode('');
        $id = 'setting_'.str_replace('.', '_', $key);

        return compact('key', 'label', 'step', 'name', 'id');
    };

    $sections = [
        'transport' => collect(App\Enums\TransportType::cases())->map(fn ($type) => $field('footprint.emission_factors.transport.'.$type->value, $type->label())),
        'factors' => collect(['electricity', 'fertilizer', 'pesticide'])->map(fn ($key) => $field('footprint.emission_factors.'.$key, __('admin.settings.labels.'.$key))),
        'footprint_weights' => collect(['co2', 'water', 'energy', 'food_miles'])->map(fn ($key) => $field('footprint.weights.'.$key, __('admin.settings.labels.'.$key))),
        'grades' => collect(['A', 'B', 'C', 'D'])->map(fn ($grade) => $field('footprint.grades.'.$grade, $grade)),
        'trust_weights' => collect(array_keys($trust['weights']))->map(fn ($key) => $field('trust.weights.'.$key, __('trust.components.'.$key))),
        'penalties' => collect(array_keys($trust['penalties']))->map(fn ($key) => $field('trust.penalties.'.$key, $key === 'max_total' ? __('admin.settings.labels.max_total') : __('trust.penalties.'.$key))),
        'local' => collect(['local_radius_km', 'local_claim_max_food_miles_km'])->map(fn ($key) => $field('trust.'.$key, __('admin.settings.labels.'.$key))),
    ];
@endphp

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('admin.settings.title') }}</h1>
        <button type="button" class="btn btn-sm btn-light shadow-sm" data-toggle="modal" data-target="#resetSettingsModal">
            <i class="fas fa-undo fa-sm"></i> {{ __('admin.settings.reset') }}</button>
    </div>

    <div class="alert alert-info" role="note">
        <i class="fas fa-info-circle mr-1"></i> {{ __('admin.settings.intro') }}
    </div>

    @foreach (['trust.weights', 'footprint.weights'] as $bag)
        @error($bag)
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror
    @endforeach

    <form method="POST" action="{{ route('admin.settings.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="row">
            @foreach ($sections as $section => $fields)
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">{{ __('admin.settings.sections.'.$section) }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-row">
                                @foreach ($fields as $input)
                                    <div class="form-group col-sm-6">
                                        <label for="{{ $input['id'] }}">{{ $input['label'] }}</label>
                                        <input type="number" step="{{ $input['step'] }}" min="0" name="{{ $input['name'] }}" id="{{ $input['id'] }}"
                                            value="{{ old($input['key'], config($input['key'])) }}"
                                            class="form-control @error($input['key']) is-invalid @enderror" required>
                                        @error($input['key'])
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('admin.settings.sections.thresholds') }}</h6>
                    </div>
                    <div class="card-body">
                        @foreach (array_keys($footprint['thresholds']) as $threshold)
                            <div class="form-row align-items-end">
                                <div class="col-12 small font-weight-bold text-gray-800">{{ __('admin.settings.labels.'.$threshold) }}</div>
                                @foreach (['best', 'worst'] as $bound)
                                    @php $input = $field("footprint.thresholds.$threshold.$bound", __('admin.settings.labels.'.$bound)); @endphp
                                    <div class="form-group col-6">
                                        <label for="{{ $input['id'] }}" class="small">{{ $input['label'] }}</label>
                                        <input type="number" step="any" min="0" name="{{ $input['name'] }}" id="{{ $input['id'] }}"
                                            value="{{ old($input['key'], config($input['key'])) }}"
                                            class="form-control @error($input['key']) is-invalid @enderror" required>
                                        @error($input['key'])
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mb-4">
            <i class="fas fa-save fa-sm mr-1"></i> {{ __('admin.settings.save') }}
        </button>
    </form>

    <!-- Reset modal -->
    <div class="modal fade" id="resetSettingsModal" tabindex="-1" role="dialog" aria-labelledby="resetSettingsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form class="modal-content" method="POST" action="{{ route('admin.settings.reset') }}">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title" id="resetSettingsModalLabel">{{ __('admin.settings.reset') }}</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="{{ __('ui.close') }}">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">{{ __('admin.settings.intro') }}</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">{{ __('ui.cancel') }}</button>
                    <button class="btn btn-danger" type="submit">{{ __('admin.settings.reset') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection
