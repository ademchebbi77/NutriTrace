@extends('layouts.layout')

@section('title', __('ai.moderation.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-1 text-gray-800">{{ __('ai.moderation.title') }}</h1>
    <p class="text-gray-600 mb-4">{{ __('ai.moderation.subtitle') }}</p>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('ai.moderation.pending', ['count' => $pending->count()]) }}</h6>
        </div>
        <div class="card-body">
            @forelse ($pending as $review)
                <div class="@if (! $loop->last) border-bottom pb-3 mb-3 @endif">
                    <div class="d-flex justify-content-between flex-wrap">
                        <div>
                            <span class="font-weight-bold">{{ $review->product?->name }}</span>
                            <span class="text-warning ml-1">{{ str_repeat('★', $review->rating) }}</span>
                            <span class="small text-gray-600">· {{ $review->user?->name }} · {{ $review->updated_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <span class="badge badge-danger align-self-start">{{ __('ai.moderation.labels.'.$review->moderation_label) }}
                            @if ($review->moderation_confidence) ({{ number_format($review->moderation_confidence * 100, 0) }} %) @endif</span>
                    </div>
                    <blockquote class="border-left pl-3 my-2 text-gray-800">« {{ $review->comment }} »</blockquote>
                    <ul class="small mb-2">
                        @foreach ($review->moderation_reasons ?? [] as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                    @if ($review->moderation_explanation)
                        <p class="small mb-2"><i class="fas fa-robot fa-sm mr-1 text-gray-500"></i> {{ $review->moderation_explanation }}</p>
                    @endif
                    <div>
                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check fa-sm"></i> {{ __('ai.moderation.approve') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times fa-sm"></i> {{ __('ai.moderation.reject') }}</button>
                        </form>
                        @if ($canExplain && ! $review->moderation_explanation)
                            <form method="POST" action="{{ route('admin.reviews.explain', $review) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-comment-dots fa-sm"></i> {{ __('ai.moderation.explain') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="mb-0 text-gray-600">{{ __('ai.moderation.none') }}</p>
            @endforelse
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('ai.moderation.history') }}</h6>
        </div>
        <div class="card-body">
            @if ($decided->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('ai.moderation.no_history') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0" data-order="[]">
                        <thead>
                            <tr>
                                <th>{{ __('ai.moderation.decided_at') }}</th>
                                <th>{{ __('reviews.fields.comment') }}</th>
                                <th>{{ __('ai.moderation.reason') }}</th>
                                <th>{{ __('ai.moderation.decision') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($decided as $review)
                                <tr>
                                    <td data-order="{{ $review->moderated_at->timestamp }}">{{ $review->moderated_at->format('d/m/Y H:i') }}<br>
                                        <span class="small text-gray-600">{{ $review->moderator?->name }}</span></td>
                                    <td>{{ $review->product?->name }} — « {{ \Illuminate\Support\Str::limit($review->comment, 120) }} »</td>
                                    <td>{{ $review->moderation_label ? __('ai.moderation.labels.'.$review->moderation_label) : '—' }}</td>
                                    <td><span class="badge badge-{{ $review->isPublished() ? 'success' : 'secondary' }}">{{ __('ai.moderation.statuses.'.$review->moderation_status) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

@endsection
