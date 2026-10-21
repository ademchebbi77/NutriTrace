@extends('layouts.public')

@section('title', __('transfers.consignment.title').' '.$transport->consignment_number)

@php
    $unit = $transport->unit ?? $transport->lot->unit;
    $gap = $transport->handoverGap();
@endphp

@section('content')
<section class="py-5">
    <div class="container" style="max-width: 820px">

        @foreach (['success', 'warning'] as $flash)
            @if (session($flash))
                <div class="alert alert-{{ $flash }}">{{ session($flash) }}</div>
            @endif
        @endforeach

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">{{ __('transfers.consignment.title') }} {{ $transport->consignment_number }}</h1>
                <p class="text-muted mb-0">{{ __('transfers.consignment.public_intro') }}</p>
            </div>
            <div aria-hidden="true">{!! $qr !!}</div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('transfers.consignment.goods') }}</dt>
                    <dd class="col-sm-8">{{ $transport->lot->product->name }} — lot {{ $transport->lot->lot_number }}</dd>
                    <dt class="col-sm-4">{{ __('transfers.consignment.sender') }}</dt>
                    <dd class="col-sm-8">{{ $transport->shipper->displayName() }} · {{ $transport->origin_label }}</dd>
                    <dt class="col-sm-4">{{ __('transfers.consignment.recipient') }}</dt>
                    <dd class="col-sm-8">{{ $transport->recipient->displayName() }} · {{ $transport->destination_label }}</dd>
                    <dt class="col-sm-4">{{ __('transfers.fields.transport_type') }}</dt>
                    <dd class="col-sm-8">{{ $transport->transport_type->label() }} · {{ number_format((float) $transport->distance_km, 1, ',', ' ') }} km · {{ $transport->departure_date->translatedFormat('d F Y') }}</dd>
                    <dt class="col-sm-4">{{ __('transfers.consignment.sent') }}</dt>
                    <dd class="col-sm-8">{{ format_quantity($transport->quantity_transported, $unit) }}</dd>
                    <dt class="col-sm-4">{{ __('transfers.consignment.handover') }}</dt>
                    <dd class="col-sm-8">
                        @if ($transport->isHandedOver())
                            {{ __('transfers.consignment.signed_by', ['name' => $transport->handedOverBy?->displayName() ?? $transport->recipient->displayName(), 'date' => $transport->handed_over_at->translatedFormat('d F Y H:i')]) }}<br>
                            {{ __('transfers.consignment.received') }} : <strong>{{ format_quantity($transport->handover_quantity, $unit) }}</strong> —
                            @if ($gap == 0)
                                <span class="text-success">{{ __('transfers.consignment.no_gap') }}</span>
                            @else
                                <span class="text-danger">{{ __('transfers.consignment.gap') }} : {{ $gap > 0 ? '+' : '−' }}{{ format_quantity(abs($gap), $unit) }}</span>
                            @endif
                            @if ($transport->handover_comment)
                                <br><em>« {{ $transport->handover_comment }} »</em>
                            @endif
                        @elseif ($transport->status === App\Enums\TransportStatus::CANCELLED)
                            <span class="text-danger">{{ __('transfers.consignment.cancelled') }}</span>
                        @else
                            <span class="text-warning">{{ __('transfers.consignment.pending') }}</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        @if ($canSign)
            <div class="card border-primary mb-4">
                <div class="card-body">
                    <h2 class="h5">{{ __('transfers.consignment.sign_title') }}</h2>
                    <p class="text-muted small">{{ __('transfers.consignment.sign_help') }}</p>
                    <form method="POST" action="{{ route('consignment.sign', $transport->consignment_token) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="handover_quantity" class="form-label">{{ __('transfers.consignment.received') }} ({{ $unit->symbol() }})</label>
                            <input type="number" step="0.001" min="0" id="handover_quantity" name="handover_quantity" required
                                value="{{ old('handover_quantity', $transport->quantity_transported) }}"
                                class="form-control @error('handover_quantity') is-invalid @enderror">
                            @error('handover_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="handover_comment" class="form-label">{{ __('transfers.consignment.comment') }}</label>
                            <textarea id="handover_comment" name="handover_comment" rows="2" maxlength="500" class="form-control">{{ old('handover_comment') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('transfers.consignment.sign') }}</button>
                    </form>
                </div>
            </div>
        @endif

        <a href="{{ $transport->lot->publicUrl() }}">{{ __('transfers.consignment.trace_link') }} →</a>
    </div>
</section>
@endsection
