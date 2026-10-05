@extends('layouts.layout')

@section('title', $product->name)

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ $product->name }}
            <span class="badge badge-{{ $product->status->color() }} align-middle">{{ $product->status->label() }}</span>
        </h1>
        <div>
            <a href="{{ area_route('products.index') }}" class="btn btn-sm btn-light shadow-sm">
                <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
            @can('update', $product)
                <a href="{{ area_route('products.edit', $product) }}" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-pen fa-sm text-white-50"></i> {{ __('ui.edit') }}</a>
            @endcan
        </div>
    </div>

    <div class="row">

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('products.image_card') }}</h6>
                </div>
                <div class="card-body text-center">
                    @if ($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="img-fluid rounded">
                    @else
                        <i class="fas fa-image fa-4x text-gray-300 my-4"></i>
                        <p class="mb-0 text-gray-600">{{ __('products.no_image') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('products.information') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('products.fields.category') }}</dt>
                        <dd class="col-sm-8">{{ $product->category->name }}</dd>

                        <dt class="col-sm-4">{{ __('products.fields.origin') }}</dt>
                        <dd class="col-sm-8">{{ $product->origin ?: '—' }}</dd>

                        <dt class="col-sm-4">{{ __('products.fields.barcode') }}</dt>
                        <dd class="col-sm-8">{{ $product->barcode ?: '—' }}</dd>

                        <dt class="col-sm-4">{{ __('products.fields.owner') }}</dt>
                        <dd class="col-sm-8">{{ $product->creator->organization?->name ?? $product->creator->name }}</dd>

                        <dt class="col-sm-4">{{ __('products.fields.created_at') }}</dt>
                        <dd class="col-sm-8">{{ $product->created_at->format('d/m/Y') }}</dd>

                        <dt class="col-sm-4">{{ __('products.fields.description') }}</dt>
                        <dd class="col-sm-8 mb-0">{!! nl2br(e($product->description ?: '—')) !!}</dd>
                    </dl>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('products.lots_card') }}</h6>
        </div>
        <div class="card-body">
            @if ($lots->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('products.no_lots') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('lots.fields.lot_number') }}</th>
                                <th>{{ __('lots.fields.quantity') }}</th>
                                <th>{{ __('lots.fields.production_date') }}</th>
                                <th>{{ __('lots.fields.holder') }}</th>
                                <th>{{ __('lots.fields.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $lot)
                                <tr>
                                    <td>
                                        @can('view', $lot)
                                            <a href="{{ area_route('lots.show', $lot) }}" class="font-weight-bold">{{ $lot->lot_number }}</a>
                                        @else
                                            <span class="font-weight-bold">{{ $lot->lot_number }}</span>
                                        @endcan
                                    </td>
                                    <td>{{ $lot->formattedQuantity() }}</td>
                                    <td>{{ $lot->production_date->format('d/m/Y') }}</td>
                                    <td>{{ $lot->currentHolder->organization?->name ?? $lot->currentHolder->name }}</td>
                                    <td><span class="badge badge-{{ $lot->status->color() }}">{{ $lot->status->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

@endsection
