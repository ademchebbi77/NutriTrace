@extends('layouts.layout')

@section('title', __('productions.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('productions.title') }}</h1>
        @can('create', App\Models\Production::class)
            <a href="{{ area_route('productions.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> {{ __('productions.add') }}</a>
        @endcan
    </div>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('productions.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($productions->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('productions.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('productions.fields.production_date') }}</th>
                                <th>{{ __('productions.fields.product') }}</th>
                                <th>{{ __('productions.fields.quantity') }}</th>
                                <th>{{ __('productions.fields.production_method') }}</th>
                                <th>{{ __('productions.fields.location') }}</th>
                                <th>{{ __('productions.fields.lot') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productions as $production)
                                <tr>
                                    <td data-order="{{ $production->production_date->timestamp }}">{{ $production->production_date->format('d/m/Y') }}</td>
                                    <td class="font-weight-bold">{{ $production->product->name }}</td>
                                    <td data-order="{{ $production->quantity }}">{{ format_quantity($production->quantity, $production->unit) }}</td>
                                    <td>{{ $production->production_method->label() }}</td>
                                    <td>{{ $production->location_city }}</td>
                                    <td>
                                        @if ($production->lot)
                                            <a href="{{ area_route('lots.show', $production->lot) }}">{{ $production->lot->lot_number }}</a>
                                            <br><span class="badge badge-{{ $production->lot->status->color() }}">{{ $production->lot->status->label() }}</span>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="{{ area_route('productions.show', $production) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }}">
                                            <i class="fas fa-eye"></i></a>
                                        @can('update', $production)
                                            <a href="{{ area_route('productions.edit', $production) }}" class="btn btn-primary btn-circle btn-sm"
                                                title="{{ __('ui.edit') }}" aria-label="{{ __('ui.edit') }}">
                                                <i class="fas fa-pen"></i></a>
                                            <button type="button" class="btn btn-danger btn-circle btn-sm" data-toggle="modal"
                                                data-target="#confirmDeleteModal"
                                                data-action="{{ area_route('productions.destroy', $production) }}"
                                                data-label="{{ $production->product->name }} - {{ $production->lot->lot_number }}"
                                                title="{{ __('ui.delete') }}" aria-label="{{ __('ui.delete') }}">
                                                <i class="fas fa-trash"></i></button>
                                        @endcan
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
