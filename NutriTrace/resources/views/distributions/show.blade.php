@extends('layouts.layout')

@section('title', __('distributions.show_title', ['number' => $distribution->lot->lot_number]))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ __('distributions.show_title', ['number' => $distribution->lot->lot_number]) }}
            <span class="badge badge-{{ $distribution->status->color() }} align-middle">{{ $distribution->status->label() }}</span>
        </h1>
        <a href="{{ area_route('distributions.index') }}" class="btn btn-sm btn-light shadow-sm">
            <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
    </div>

    @can('respond', $distribution)
        <div class="alert alert-warning d-flex align-items-center justify-content-between" role="alert">
            <span><i class="fas fa-inbox mr-1"></i> {{ __('distributions.to_receive') }}</span>
            <a href="{{ route('distributeur.receptions.index') }}" class="btn btn-sm btn-warning">{{ __('distributions.go_to_receptions') }}</a>
        </div>
    @endcan

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('distributions.information') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('distributions.fields.lot') }}</dt>
                        <dd class="col-sm-8">
                            @can('view', $distribution->lot)
                                <a href="{{ area_route('lots.show', $distribution->lot) }}">{{ $distribution->lot->lot_number }}</a>
                            @else
                                {{ $distribution->lot->lot_number }}
                            @endcan
                            &middot; {{ $distribution->lot->product->name }}
                        </dd>

                        <dt class="col-sm-4">{{ __('distributions.fields.sender') }}</dt>
                        <dd class="col-sm-8">{{ $distribution->sender->displayName() }}</dd>

                        <dt class="col-sm-4">{{ __('distributions.fields.distributor') }}</dt>
                        <dd class="col-sm-8">{{ $distribution->distributor->displayName() }}</dd>

                        <dt class="col-sm-4">{{ __('distributions.fields.destination') }}</dt>
                        <dd class="col-sm-8">{{ $distribution->destination }}</dd>

                        <dt class="col-sm-4">{{ __('distributions.fields.quantity') }}</dt>
                        <dd class="col-sm-8">{{ format_quantity($distribution->quantity, $distribution->lot->unit) }}</dd>

                        <dt class="col-sm-4">{{ __('distributions.fields.distribution_date') }}</dt>
                        <dd class="col-sm-8">{{ $distribution->distribution_date->format('d/m/Y') }}</dd>

                        <dt class="col-sm-4">{{ __('distributions.fields.reception_date') }}</dt>
                        <dd class="col-sm-8 mb-0">{{ $distribution->reception_date?->format('d/m/Y') ?? '—' }}</dd>
                    </dl>
                    @if ($distribution->rejection_reason)
                        <div class="alert alert-danger mt-3 mb-0">
                            <strong>{{ __('distributions.fields.reason') }}</strong> {{ $distribution->rejection_reason }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('distributions.sales.history') }}</h6>
                </div>
                <div class="card-body">
                    @forelse ($sales as $sale)
                        <div class="d-flex justify-content-between @if (! $loop->last) border-bottom pb-2 mb-2 @endif">
                            <span>{{ $sale->description }}</span>
                            <span class="small text-gray-600 text-nowrap ml-3">{{ $sale->occurred_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @empty
                        <p class="mb-0 text-gray-600">{{ __('distributions.sales.history_empty') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @if ($distribution->isAccepted())
                <div class="card border-left-success shadow mb-4">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">{{ __('distributions.fields.remaining') }}</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">{{ $distribution->lot->formattedQuantity() }}</div>
                    </div>
                </div>
            @endif

            @can('markInStore', $distribution)
                <form method="POST" action="{{ route('distributeur.distributions.in-store', $distribution) }}" class="mb-4">
                    @csrf
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-store fa-sm mr-1"></i> {{ __('distributions.mark_in_store') }}
                    </button>
                </form>
            @endcan

            @can('sell', $distribution)
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">{{ __('distributions.sales.record') }}</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('distributeur.sales.store', $distribution) }}" novalidate>
                            @csrf
                            <div class="form-group">
                                <label for="quantity">{{ __('distributions.sales.quantity') }}</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0.01" max="{{ $distribution->lot->quantity }}" name="quantity" id="quantity"
                                        value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">{{ $distribution->lot->unit->symbol() }}</span>
                                    </div>
                                    @error('quantity')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-cash-register fa-sm mr-1"></i> {{ __('distributions.sales.record') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endcan

            @if ($distribution->isAccepted() && auth()->user()->can('view', $distribution->lot) && area_has_route('labels.show'))
                <a href="{{ area_route('labels.show', $distribution->lot) }}" class="btn btn-light btn-block mb-4">
                    <i class="fas fa-qrcode fa-sm mr-1"></i> {{ __('distributions.labels.print') }}</a>
            @endif
        </div>

    </div>

@endsection
