@extends('layouts.layout')

@section('title', __('products.categories.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('products.categories.title') }}</h1>

    <div class="row">

        <div class="col-lg-8">
            <!-- DataTales Example -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('products.categories.list') }}</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered datatable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>{{ __('products.categories.name') }}</th>
                                    <th>{{ __('products.categories.products_count') }}</th>
                                    <th class="no-sort">{{ __('ui.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)
                                    <tr>
                                        <td class="font-weight-bold">{{ $category->name }}</td>
                                        <td>{{ $category->products_count }}</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-primary btn-circle btn-sm"
                                                title="{{ __('ui.edit') }}" aria-label="{{ __('ui.edit') }} {{ $category->name }}">
                                                <i class="fas fa-pen"></i></a>
                                            @if ($category->products_count === 0)
                                                <button type="button" class="btn btn-danger btn-circle btn-sm" data-toggle="modal"
                                                    data-target="#confirmDeleteModal"
                                                    data-action="{{ route('admin.categories.destroy', $category) }}"
                                                    data-label="{{ $category->name }}"
                                                    title="{{ __('ui.delete') }}" aria-label="{{ __('ui.delete') }} {{ $category->name }}">
                                                    <i class="fas fa-trash"></i></button>
                                            @else
                                                <button type="button" class="btn btn-secondary btn-circle btn-sm" disabled
                                                    title="{{ __('products.categories.in_use') }}" aria-label="{{ __('products.categories.in_use') }}">
                                                    <i class="fas fa-trash"></i></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('products.categories.add') }}</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.categories.store') }}" novalidate>
                        @csrf
                        <div class="form-group">
                            <label for="name">{{ __('products.categories.name') }}</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" maxlength="100"
                                class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-plus fa-sm mr-1"></i> {{ __('products.categories.add') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

@endsection
