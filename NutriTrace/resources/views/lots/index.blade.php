@extends('layouts.layout')

@section('title', __('lots.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('lots.title') }}</h1>
        @can('create', App\Models\Production::class)
            <a href="{{ area_route('productions.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> {{ __('productions.add') }}</a>
        @endcan
    </div>

    <p class="text-gray-600"><i class="fas fa-info-circle mr-1"></i>{{ __('lots.created_by_production') }}</p>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('lots.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($lots->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('lots.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('lots.fields.lot_number') }}</th>
                                <th>{{ __('lots.fields.product') }}</th>
                                <th>{{ __('lots.fields.quantity') }}</th>
                                <th>{{ __('lots.fields.production_date') }}</th>
                                <th>{{ __('lots.fields.expiration_date') }}</th>
                                <th>{{ __('lots.fields.holder') }}</th>
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
                                    <td data-order="{{ $lot->production_date->timestamp }}">{{ $lot->production_date->format('d/m/Y') }}</td>
                                    <td data-order="{{ $lot->expiration_date?->timestamp ?? 0 }}">
                                        {{ $lot->expiration_date?->format('d/m/Y') ?? '—' }}
                                        @if ($lot->isExpired())
                                            <span class="badge badge-danger">{{ __('lots.expired') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $lot->currentHolder->organization?->name ?? $lot->currentHolder->name }}
                                        @if ($lot->isHeldBy(auth()->user()))
                                            <br><span class="small text-success"><i class="fas fa-hand-holding fa-sm"></i> {{ __('lots.held_by_you') }}</span>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-{{ $lot->status->color() }}">{{ $lot->status->label() }}</span></td>
                                    <td class="text-nowrap">
                                        <a href="{{ area_route('lots.show', $lot) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $lot->lot_number }}">
                                            <i class="fas fa-eye"></i></a>
                                        @can('update', $lot)
                                            <a href="{{ area_route('lots.edit', $lot) }}" class="btn btn-primary btn-circle btn-sm"
                                                title="{{ __('ui.edit') }}" aria-label="{{ __('ui.edit') }} {{ $lot->lot_number }}">
                                                <i class="fas fa-pen"></i></a>
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
