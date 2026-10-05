@extends('layouts.layout')

@section('title', __('distributions.labels.label_title', ['number' => $lot->lot_number]))

@push('styles')
    <style>
        .lot-label {
            max-width: 26rem;
            border: 2px dashed #b7b9cc;
        }

        .lot-label svg {
            width: 13rem;
            height: 13rem;
        }

        /* Print the label alone, without the back office chrome. */
        @media print {
            #accordionSidebar, .topbar, .sticky-footer, .scroll-to-top, .no-print {
                display: none !important;
            }

            #content-wrapper, body {
                background: #fff !important;
            }

            .lot-label {
                border: 1px solid #000;
                box-shadow: none !important;
                margin: 0 auto;
            }
        }
    </style>
@endpush

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4 no-print">
        <h1 class="h3 mb-0 text-gray-800">{{ __('distributions.labels.label_title', ['number' => $lot->lot_number]) }}</h1>
        <div>
            <a href="{{ $lot->publicUrl() }}" class="btn btn-sm btn-light shadow-sm" target="_blank" rel="noopener">
                <i class="fas fa-external-link-alt fa-sm"></i> {{ __('distributions.labels.open_public') }}</a>
            <button type="button" class="btn btn-sm btn-primary shadow-sm" onclick="window.print()">
                <i class="fas fa-print fa-sm text-white-50"></i> {{ __('distributions.labels.print') }}</button>
        </div>
    </div>

    <div class="card shadow lot-label mx-auto mb-4">
        <div class="card-body text-center">
            <div class="h5 font-weight-bold text-gray-900 mb-0">{{ $lot->product->name }}</div>
            <div class="text-gray-700 mb-3">{{ $lot->product->origin }}</div>

            <div role="img" aria-label="QR code {{ $lot->lot_number }}">{!! $qr !!}</div>

            <div class="h5 font-weight-bold text-gray-900 mt-3 mb-1">{{ $lot->lot_number }}</div>
            <div class="small text-gray-800">
                {{ __('distributions.labels.produced') }} {{ $lot->production_date->format('d/m/Y') }}
                @if ($lot->expiration_date)
                    &middot; {{ __('distributions.labels.best_before') }} {{ $lot->expiration_date->format('d/m/Y') }}
                @endif
            </div>
            <p class="small text-gray-800 mt-3 mb-1">{{ __('distributions.labels.scan') }}</p>
            <div class="small text-gray-600">{{ config('app.name') }} &middot; {{ __('ui.tagline') }}</div>
        </div>
    </div>

@endsection
