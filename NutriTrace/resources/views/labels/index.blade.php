@extends('layouts.layout')

@section('title', __('distributions.labels.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('distributions.labels.title') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('distributions.labels.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($lots->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('distributions.labels.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('lots.fields.lot_number') }}</th>
                                <th>{{ __('lots.fields.product') }}</th>
                                <th>{{ __('lots.fields.quantity') }}</th>
                                <th>{{ __('lots.fields.status') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $lot)
                                <tr>
                                    <td><a href="{{ area_route('lots.show', $lot) }}" class="font-weight-bold">{{ $lot->lot_number }}</a></td>
                                    <td>{{ $lot->product->name }}</td>
                                    <td data-order="{{ $lot->quantity }}">{{ $lot->formattedQuantity() }}</td>
                                    <td><span class="badge badge-{{ $lot->status->color() }}">{{ $lot->status->label() }}</span></td>
                                    <td>
                                        <a href="{{ area_route('labels.show', $lot) }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-qrcode fa-sm"></i> {{ __('distributions.labels.print') }}</a>
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
