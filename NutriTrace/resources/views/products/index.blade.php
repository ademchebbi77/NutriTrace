@extends('layouts.layout')

@section('title', __('products.title'))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">{{ __('products.title') }}</h1>
        @can('create', App\Models\Product::class)
            <a href="{{ area_route('products.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> {{ __('products.add') }}</a>
        @endcan
    </div>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('products.list') }}</h6>
        </div>
        <div class="card-body">
            @if ($products->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('products.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('products.fields.name') }}</th>
                                <th>{{ __('products.fields.category') }}</th>
                                <th>{{ __('products.fields.origin') }}</th>
                                @if (auth()->user()->isAdmin())
                                    <th>{{ __('products.fields.owner') }}</th>
                                @endif
                                <th>{{ __('products.fields.lots_count') }}</th>
                                <th>{{ __('products.fields.status') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td>
                                        <a href="{{ area_route('products.show', $product) }}" class="font-weight-bold">{{ $product->name }}</a>
                                        @if ($product->barcode)
                                            <br><span class="small text-gray-600"><i class="fas fa-barcode fa-sm"></i> {{ $product->barcode }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $product->category->name }}</td>
                                    <td>{{ $product->origin }}</td>
                                    @if (auth()->user()->isAdmin())
                                        <td>{{ $product->creator->organization?->name ?? $product->creator->name }}</td>
                                    @endif
                                    <td>{{ $product->lots_count }}</td>
                                    <td><span class="badge badge-{{ $product->status->color() }}">{{ $product->status->label() }}</span></td>
                                    <td class="text-nowrap">
                                        <a href="{{ area_route('products.show', $product) }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $product->name }}">
                                            <i class="fas fa-eye"></i></a>
                                        @can('update', $product)
                                            <a href="{{ area_route('products.edit', $product) }}" class="btn btn-primary btn-circle btn-sm"
                                                title="{{ __('ui.edit') }}" aria-label="{{ __('ui.edit') }} {{ $product->name }}">
                                                <i class="fas fa-pen"></i></a>
                                        @endcan
                                        @if ($product->isOwnedBy(auth()->user()))
                                            @if ($product->lots_count === 0)
                                                <button type="button" class="btn btn-danger btn-circle btn-sm" data-toggle="modal"
                                                    data-target="#confirmDeleteModal"
                                                    data-action="{{ area_route('products.destroy', $product) }}"
                                                    data-label="{{ $product->name }}"
                                                    title="{{ __('ui.delete') }}" aria-label="{{ __('ui.delete') }} {{ $product->name }}">
                                                    <i class="fas fa-trash"></i></button>
                                            @else
                                                <button type="button" class="btn btn-secondary btn-circle btn-sm" disabled
                                                    title="{{ __('products.delete_blocked') }}" aria-label="{{ __('products.delete_blocked') }}">
                                                    <i class="fas fa-trash"></i></button>
                                            @endif
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
