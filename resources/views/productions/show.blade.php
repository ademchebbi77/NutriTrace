@extends('layouts.layout')

@section('title', __('productions.show_title', ['date' => $production->production_date->format('d/m/Y')]))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ $production->product->name }}
            <small class="text-gray-600">{{ $production->production_date->format('d/m/Y') }}</small>
        </h1>
        <div>
            <a href="{{ area_route('productions.index') }}" class="btn btn-sm btn-light shadow-sm">
                <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
            @can('update', $production)
                <a href="{{ area_route('productions.edit', $production) }}" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-pen fa-sm text-white-50"></i> {{ __('ui.edit') }}</a>
            @endcan
        </div>
    </div>

    @if ($production->producer_id === auth()->id() && ! $production->isEditable())
        <div class="alert alert-secondary" role="note">
            <i class="fas fa-lock mr-1"></i> {{ __('productions.locked') }}
        </div>
    @endif

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('productions.sections.product') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ __('productions.fields.product') }}</dt>
                        <dd class="col-sm-7">{{ $production->product->name }} ({{ $production->product->category->name }})</dd>

                        <dt class="col-sm-5">{{ __('productions.fields.quantity') }}</dt>
                        <dd class="col-sm-7">{{ format_quantity($production->quantity, $production->unit) }}</dd>

                        <dt class="col-sm-5">{{ __('productions.fields.production_method') }}</dt>
                        <dd class="col-sm-7">{{ $production->production_method->label() }}</dd>

                        <dt class="col-sm-5">{{ __('productions.fields.producer') }}</dt>
                        <dd class="col-sm-7">{{ $production->producer->organization?->name ?? $production->producer->name }}</dd>

                        <dt class="col-sm-5">{{ __('productions.fields.location') }}</dt>
                        <dd class="col-sm-7 mb-0">
                            {{ collect([$production->location_address, $production->location_city])->filter()->join(', ') }}
                            @if ($production->hasCoordinates())
                                <br><span class="small text-gray-600"><i class="fas fa-map-marker-alt fa-sm"></i>
                                    {{ $production->latitude }}, {{ $production->longitude }}</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('productions.sections.resources') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        @foreach (App\Models\Production::RESOURCE_KEYS as $key)
                            <dt class="col-sm-5">{{ __('productions.fields.'.$key) }}</dt>
                            <dd class="col-sm-7">
                                @isset($production->resources_used[$key])
                                    {{ number_format((float) $production->resources_used[$key], 2, ',', ' ') }}
                                @else
                                    <span class="text-gray-500">{{ __('productions.fields.not_declared') }}</span>
                                @endisset
                            </dd>
                        @endforeach
                        @if (! empty($production->resources_used['notes']))
                            <dt class="col-sm-5">{{ __('productions.fields.notes') }}</dt>
                            <dd class="col-sm-7 mb-0">{{ $production->resources_used['notes'] }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @if ($production->lot)
                <div class="card border-left-primary shadow mb-4">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">{{ __('productions.sections.lot') }}</div>
                        <div class="h5 mb-2 font-weight-bold text-gray-800">{{ $production->lot->lot_number }}</div>
                        <span class="badge badge-{{ $production->lot->status->color() }}">{{ $production->lot->status->label() }}</span>
                        <dl class="row mt-3 mb-0 small">
                            <dt class="col-6">{{ __('lots.fields.quantity') }}</dt>
                            <dd class="col-6">{{ $production->lot->formattedQuantity() }}</dd>
                            <dt class="col-6">{{ __('lots.fields.holder') }}</dt>
                            <dd class="col-6">{{ $production->lot->currentHolder->organization?->name ?? $production->lot->currentHolder->name }}</dd>
                            <dt class="col-6">{{ __('lots.fields.expiration_date') }}</dt>
                            <dd class="col-6 mb-0">{{ $production->lot->expiration_date?->format('d/m/Y') ?? __('lots.fields.none') }}</dd>
                        </dl>
                        <a href="{{ area_route('lots.show', $production->lot) }}" class="btn btn-primary btn-sm mt-3">
                            <i class="fas fa-boxes fa-sm mr-1"></i> {{ __('ui.view') }}</a>
                    </div>
                </div>
            @endif
        </div>

    </div>

@endsection
