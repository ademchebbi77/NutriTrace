@extends('layouts.layout')

@section('title', __('transfers.transports.show_title', ['number' => $transport->lot->lot_number]))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ __('transfers.transports.show_title', ['number' => $transport->lot->lot_number]) }}
            <span class="badge badge-{{ $transport->status->color() }} align-middle">{{ $transport->status->label() }}</span>
        </h1>
        <div class="mt-2 mt-sm-0">
            @can('update', $transport)
                <a href="{{ area_route('transports.edit', $transport) }}" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-pen fa-sm"></i> {{ __('ui.edit') }}</a>
            @endcan
            @can('delete', $transport)
                <button type="button" class="btn btn-sm btn-danger shadow-sm" data-toggle="modal"
                    data-target="#confirmDeleteModal"
                    data-action="{{ area_route('transports.destroy', $transport) }}"
                    data-label="{{ __('transfers.transports.cancel_label', ['number' => $transport->lot->lot_number]) }}">
                    <i class="fas fa-ban fa-sm"></i> {{ __('transfers.transports.cancel') }}</button>
            @endcan
            <a href="{{ area_route('transports.index') }}" class="btn btn-sm btn-light shadow-sm">
                <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
        </div>
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

        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.transports.actors') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ __('transfers.fields.sender') }}</dt>
                        <dd class="col-sm-7">{{ $transport->shipper->displayName() }}</dd>

                        <dt class="col-sm-5">{{ __('transfers.fields.recipient') }}</dt>
                        <dd class="col-sm-7 mb-0">{{ $transport->recipient?->displayName() ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            @include('ai.partials.transport-analysis')
        </div>

    </div>

    @if (config('ai.enabled'))
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-comments fa-sm mr-1"></i> {{ __('ai.assistant.title') }}</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted">{{ __('ai.assistant.subtitle') }}</p>
                @include('ai.partials.assistant', ['url' => area_route('transports.assistant', $transport), 'available' => $chatAvailable])
            </div>
        </div>
    @endif

@endsection
