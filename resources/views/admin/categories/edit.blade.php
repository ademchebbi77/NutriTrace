@extends('layouts.layout')

@section('title', __('products.categories.edit_title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('products.categories.edit_title') }}</h1>

    <div class="row">
        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.categories.update', $category) }}" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="name">{{ __('products.categories.name') }}</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $category->name) }}" maxlength="100"
                                class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
                        </button>
                        <a href="{{ route('admin.categories.index') }}" class="btn btn-light">{{ __('ui.cancel') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
