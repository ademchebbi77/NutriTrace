@extends('layouts.layout')

@section('title', __('distributions.sales.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('distributions.sales.title') }}</h1>

    @error('quantity')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('distributions.sales.on_shelf') }}</h6>
        </div>
        <div class="card-body">
            @forelse ($onShelf as $distribution)
                <div class="row align-items-center @if (! $loop->last) border-bottom pb-3 mb-3 @endif">
                    <div class="col-lg-6">
                        <a href="{{ route('distributeur.distributions.show', $distribution) }}" class="font-weight-bold">{{ $distribution->lot->lot_number }}</a>
                        &middot; {{ $distribution->lot->product->name }}
                        <div class="small text-gray-600">
                            {{ $distribution->destination }} &middot; {{ __('distributions.fields.remaining') }} :
                            <strong>{{ $distribution->lot->formattedQuantity() }}</strong>
                        </div>
                    </div>
                    <div class="col-lg-6 mt-2 mt-lg-0">
                        <form method="POST" action="{{ route('distributeur.sales.store', $distribution) }}" class="form-inline justify-content-lg-end" novalidate>
                            @csrf
                            <label class="sr-only" for="quantity_{{ $distribution->id }}">{{ __('distributions.sales.quantity') }} ({{ $distribution->lot->lot_number }})</label>
                            <div class="input-group input-group-sm mr-2">
                                <input type="number" step="0.01" min="0.01" max="{{ $distribution->lot->quantity }}" name="quantity"
                                    id="quantity_{{ $distribution->id }}" class="form-control" placeholder="{{ __('distributions.sales.quantity') }}" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">{{ $distribution->lot->unit->symbol() }}</span>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-cash-register fa-sm"></i> {{ __('distributions.sales.record') }}
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="mb-0 text-gray-600">{{ __('distributions.sales.on_shelf_empty') }}</p>
            @endforelse
        </div>
    </div>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('distributions.sales.history') }}</h6>
        </div>
        <div class="card-body">
            @if ($sales->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('distributions.sales.history_empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('distributions.sales.date') }}</th>
                                <th>{{ __('distributions.fields.lot') }}</th>
                                <th>{{ __('distributions.sales.quantity') }}</th>
                                <th>{{ __('distributions.fields.destination') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sales as $sale)
                                <tr>
                                    <td data-order="{{ $sale->occurred_at->timestamp }}">{{ $sale->occurred_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="font-weight-bold">{{ $sale->lot->lot_number }}</span>
                                        <br><span class="small text-gray-600">{{ $sale->lot->product->name }}</span>
                                    </td>
                                    <td data-order="{{ $sale->metadata['quantity'] ?? 0 }}">{{ format_quantity((float) ($sale->metadata['quantity'] ?? 0), $sale->lot->unit) }}</td>
                                    <td>{{ $sale->location }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

@endsection
