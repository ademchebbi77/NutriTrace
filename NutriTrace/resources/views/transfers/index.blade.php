@extends('layouts.layout')

@section('title', __('transfers.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('transfers.title') }}</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.ready_title') }}</h6>
        </div>
        <div class="card-body">
            @forelse ($sendable as $lot)
                <div class="d-flex align-items-center justify-content-between @if (! $loop->last) border-bottom pb-2 mb-2 @endif">
                    <div>
                        <a href="{{ area_route('lots.show', $lot) }}" class="font-weight-bold">{{ $lot->lot_number }}</a>
                        <span class="text-gray-600">&middot; {{ $lot->product->name }} &middot; {{ $lot->formattedQuantity() }}</span>
                    </div>
                    <a href="{{ area_route('transfers.create', $lot) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-paper-plane fa-sm mr-1"></i> {{ __('transfers.send') }}</a>
                </div>
            @empty
                <p class="mb-0 text-gray-600">{{ __('transfers.ready_empty') }}</p>
            @endforelse
        </div>
    </div>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.sent_list') }}</h6>
        </div>
        <div class="card-body">
            @if ($handovers->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('transfers.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('transfers.fields.sent_at') }}</th>
                                <th>{{ __('transfers.fields.lot') }}</th>
                                <th>{{ __('transfers.fields.recipient') }}</th>
                                <th>{{ __('transfers.fields.quantity') }}</th>
                                <th>{{ __('transfers.fields.transport_type') }}</th>
                                <th>{{ __('transfers.fields.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($handovers as $handover)
                                <tr>
                                    <td data-order="{{ $handover['sent_at']->timestamp }}">{{ $handover['sent_at']->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ area_route('lots.show', $handover['lot']) }}" class="font-weight-bold">{{ $handover['lot']->lot_number }}</a>
                                        <br><span class="small text-gray-600">{{ $handover['lot']->product->name }}</span>
                                    </td>
                                    <td>
                                        {{ $handover['recipient']->displayName() }}
                                        <br><span class="small text-gray-600">{{ $handover['recipient']->role->label() }}</span>
                                    </td>
                                    <td data-order="{{ $handover['quantity'] }}">{{ format_quantity($handover['quantity'], $handover['lot']->unit) }}</td>
                                    <td>
                                        @if ($handover['transport'])
                                            <a href="{{ area_route('transports.show', $handover['transport']) }}">
                                                <i class="fas {{ $handover['transport']->transport_type->icon() }} fa-sm"></i>
                                                {{ $handover['transport']->transport_type->label() }}</a>
                                            @if ($handover['transport']->distance_km !== null)
                                                <br><span class="small text-gray-600">{{ number_format($handover['transport']->distance_km, 0, ',', ' ') }} km</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $handover['status_color'] }}">{{ $handover['status_label'] }}</span>
                                        @if ($handover['rejection_reason'])
                                            <br><span class="small text-danger">{{ __('transfers.fields.reason') }} {{ $handover['rejection_reason'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

@endsection
