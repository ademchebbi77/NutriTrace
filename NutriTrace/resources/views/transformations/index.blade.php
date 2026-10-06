@extends('layouts.layout')

@section('title', __('transformations.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('transformations.title') }}</h1>
        @can('create', App\Models\Transformation::class)
            <a href="{{ area_route('transformations.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> {{ __('transformations.add') }}</a>
        @endcan
    </div>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('transformations.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($transformations->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('transformations.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('transformations.fields.date') }}</th>
                                <th>{{ __('transformations.fields.inputs') }}</th>
                                <th>{{ __('transformations.fields.output') }}</th>
                                <th>{{ __('transformations.fields.yield') }}</th>
                                @if (auth()->user()->isAdmin())
                                    <th>{{ __('transformations.fields.transformer') }}</th>
                                @endif
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transformations as $transformation)
                                <tr>
                                    <td data-order="{{ $transformation->transformation_date->timestamp }}">{{ $transformation->transformation_date->format('d/m/Y') }}</td>
                                    <td>
                                        @foreach ($transformation->inputs as $input)
                                            <div>{{ $input->lot->lot_number }} &middot; {{ $input->lot->product->name }}
                                                <span class="small text-gray-600">({{ format_quantity($input->quantity_used, $input->lot->unit) }})</span></div>
                                        @endforeach
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{ $transformation->outputLot->lot_number }}</span>
                                        <br><span class="small text-gray-600">{{ $transformation->outputLot->product->name }}
                                            &middot; {{ format_quantity($transformation->output_quantity, $transformation->outputLot->unit) }}</span>
                                    </td>
                                    <td data-order="{{ $transformation->yieldPercent() ?? 0 }}">
                                        {{ $transformation->yieldPercent() !== null ? number_format($transformation->yieldPercent(), 1, ',', ' ').' %' : '—' }}
                                    </td>
                                    @if (auth()->user()->isAdmin())
                                        <td>{{ $transformation->transformer->displayName() }}</td>
                                    @endif
                                    <td>
                                        <a href="{{ area_route('transformations.show', $transformation) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $transformation->outputLot->lot_number }}">
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
