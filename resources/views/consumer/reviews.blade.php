@extends('layouts.layout')

@section('title', __('reviews.mine.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('reviews.mine.title') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('reviews.mine.title') }}</h6>
        </div>
        <div class="card-body">
            @if ($reviews->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('reviews.mine.empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0" data-order="[]">
                        <thead>
                            <tr>
                                <th>{{ __('reviews.fields.date') }}</th>
                                <th>{{ __('reviews.fields.product') }}</th>
                                <th>{{ __('reviews.fields.rating') }}</th>
                                <th>{{ __('reviews.fields.comment') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reviews as $review)
                                <tr>
                                    <td data-order="{{ $review->updated_at->timestamp }}">{{ $review->updated_at->format('d/m/Y') }}</td>
                                    <td>
                                        <a href="{{ $review->lot && $review->product->isPubliclyVisible() ? $review->lot->publicUrl() : route('catalog.show', $review->product) }}"
                                            class="font-weight-bold">{{ $review->product->name }}</a>
                                    </td>
                                    <td data-order="{{ $review->rating }}">
                                        <span class="text-warning" aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                        <span class="sr-only">{{ __('reviews.stars', ['count' => $review->rating]) }}</span>
                                    </td>
                                    <td>{{ $review->comment }}</td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-circle btn-sm" data-toggle="modal"
                                            data-target="#confirmDeleteModal" data-action="{{ route('reviews.destroy', $review) }}"
                                            data-label="{{ $review->product->name }}"
                                            title="{{ __('ui.delete') }}" aria-label="{{ __('ui.delete') }} {{ $review->product->name }}">
                                            <i class="fas fa-trash"></i></button>
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
