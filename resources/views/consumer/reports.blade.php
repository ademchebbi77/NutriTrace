@extends('layouts.layout')

@section('title', __('reports.mine.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('reports.mine.title') }}</h1>

    @forelse ($reports as $report)
        <div class="card shadow mb-4 border-left-{{ $report->status->color() }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="font-weight-bold text-gray-800">{{ $report->targetLabel() }}</div>
                        <div class="small text-gray-600">{{ $report->type->label() }} &middot; {{ $report->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                    <span class="badge badge-{{ $report->status->color() }}">{{ $report->status->label() }}</span>
                </div>
                <p class="mt-3 mb-2">{!! nl2br(e($report->description)) !!}</p>
                <div class="small font-weight-bold text-gray-800">{{ __('reports.fields.response') }}</div>
                <div class="small">{{ $report->admin_response ?: __('reports.fields.no_response') }}</div>
            </div>
        </div>
    @empty
        <div class="card shadow mb-4">
            <div class="card-body">
                <p class="mb-0 text-gray-600">{{ __('reports.mine.empty') }}</p>
            </div>
        </div>
    @endforelse

@endsection
