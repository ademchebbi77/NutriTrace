@extends('layouts.layout')

@section('title', __('distributions.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('distributions.title') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('distributions.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($distributions->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('distributions.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('distributions.fields.distribution_date') }}</th>
                                <th>{{ __('distributions.fields.lot') }}</th>
                                <th>{{ __('distributions.fields.sender') }}</th>
                                <th>{{ __('distributions.fields.destination') }}</th>
                                <th>{{ __('distributions.fields.remaining') }}</th>
                                <th>{{ __('distributions.fields.status') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($distributions as $distribution)
                                <tr>
                                    <td data-order="{{ $distribution->distribution_date->timestamp }}">{{ $distribution->distribution_date->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="font-weight-bold">{{ $distribution->lot->lot_number }}</span>
                                        <br><span class="small text-gray-600">{{ $distribution->lot->product->name }}</span>
                                    </td>
                                    <td>{{ $distribution->sender->displayName() }}</td>
                                    <td>{{ $distribution->destination }}</td>
                                    <td data-order="{{ $distribution->lot->quantity }}">
                                        {{ $distribution->isAccepted() ? $distribution->lot->formattedQuantity() : '—' }}
                                        <br><span class="small text-gray-600">/ {{ format_quantity($distribution->quantity, $distribution->lot->unit) }}</span>
                                    </td>
                                    <td><span class="badge badge-{{ $distribution->status->color() }}">{{ $distribution->status->label() }}</span></td>
                                    <td class="text-nowrap">
                                        <a href="{{ area_route('distributions.show', $distribution) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $distribution->lot->lot_number }}">
                                            <i class="fas fa-eye"></i></a>
                                        @can('markInStore', $distribution)
                                            <form method="POST" action="{{ route('distributeur.distributions.in-store', $distribution) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-store fa-sm"></i> {{ __('distributions.mark_in_store') }}
                                                </button>
                                            </form>
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
