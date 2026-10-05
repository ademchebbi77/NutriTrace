@extends('layouts.layout')

@section('title', __('transfers.transports.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('transfers.transports.title') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.transports.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($transports->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('transfers.transports.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('transfers.fields.departure_date') }}</th>
                                <th>{{ __('transfers.fields.lot') }}</th>
                                <th>{{ __('transfers.fields.origin') }}</th>
                                <th>{{ __('transfers.fields.destination') }}</th>
                                <th>{{ __('transfers.fields.transport_type') }}</th>
                                <th>{{ __('transfers.fields.distance_km') }}</th>
                                <th>{{ __('transfers.fields.status') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transports as $transport)
                                <tr>
                                    <td data-order="{{ $transport->departure_date->timestamp }}">{{ $transport->departure_date->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="font-weight-bold">{{ $transport->lot->lot_number }}</span>
                                        <br><span class="small text-gray-600">{{ $transport->lot->product->name }}
                                            &middot; {{ format_quantity($transport->quantity_transported, $transport->unit) }}</span>
                                    </td>
                                    <td>{{ $transport->origin_label }}</td>
                                    <td>{{ $transport->destination_label }}</td>
                                    <td><i class="fas {{ $transport->transport_type->icon() }} fa-sm"></i> {{ $transport->transport_type->label() }}</td>
                                    <td data-order="{{ $transport->distance_km ?? -1 }}">
                                        {{ $transport->distance_km !== null ? number_format($transport->distance_km, 0, ',', ' ').' km' : '—' }}
                                    </td>
                                    <td><span class="badge badge-{{ $transport->status->color() }}">{{ $transport->status->label() }}</span></td>
                                    <td>
                                        <a href="{{ area_route('transports.show', $transport) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $transport->lot->lot_number }}">
                                            <i class="fas fa-eye"></i></a>
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
