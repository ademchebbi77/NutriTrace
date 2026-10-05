@extends('layouts.layout')

@section('title', __('transfers.receptions.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('transfers.receptions.title') }}</h1>

    @if ($errors->has('rejection_reason'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('rejection_reason') }}</div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.receptions.pending') }}</h6>
        </div>
        <div class="card-body">
            @forelse ($pending as $item)
                <div class="row align-items-center @if (! $loop->last) border-bottom pb-3 mb-3 @endif">
                    <div class="col-lg-7">
                        <div class="font-weight-bold text-gray-800">
                            {{ $item['lot']->lot_number }} &middot; {{ $item['lot']->product->name }}
                            &middot; {{ format_quantity($item['quantity'], $item['lot']->unit) }}
                        </div>
                        <div class="small text-gray-600">
                            {{ __('transfers.fields.sender') }} : {{ $item['sender']->displayName() }}
                            @if ($item['transport'])
                                &middot; <i class="fas {{ $item['transport']->transport_type->icon() }} fa-sm"></i>
                                {{ $item['transport']->transport_type->label() }}
                                @if ($item['transport']->distance_km !== null)
                                    ({{ number_format($item['transport']->distance_km, 0, ',', ' ') }} km)
                                @endif
                                &middot; {{ $item['transport']->departure_date->format('d/m/Y H:i') }}
                            @endif
                        </div>
                        @if ($item['note'])
                            <div class="small font-italic mt-1">« {{ $item['note'] }} »</div>
                        @endif
                    </div>
                    <div class="col-lg-5 text-lg-right mt-2 mt-lg-0">
                        <form method="POST" action="{{ $item['accept_url'] }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check fa-sm"></i> {{ __('transfers.receptions.confirm') }}
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#refuseModal"
                            data-action="{{ $item['reject_url'] }}" data-label="{{ $item['lot']->lot_number }}">
                            <i class="fas fa-times fa-sm"></i> {{ __('transfers.receptions.refuse') }}
                        </button>
                    </div>
                </div>
            @empty
                <p class="mb-0 text-gray-600">{{ __('transfers.receptions.pending_empty') }}</p>
            @endforelse
        </div>
    </div>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('transfers.receptions.history') }}</h6>
        </div>
        <div class="card-body">
            @if ($history->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('transfers.receptions.history_empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>{{ __('transfers.fields.sent_at') }}</th>
                                <th>{{ __('transfers.fields.lot') }}</th>
                                <th>{{ __('transfers.fields.sender') }}</th>
                                <th>{{ __('transfers.fields.quantity') }}</th>
                                <th>{{ __('transfers.fields.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $item)
                                <tr>
                                    <td data-order="{{ $item['sent_at']->timestamp }}">{{ $item['sent_at']->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ area_route('lots.show', $item['lot']) }}" class="font-weight-bold">{{ $item['lot']->lot_number }}</a>
                                        <br><span class="small text-gray-600">{{ $item['lot']->product->name }}</span>
                                    </td>
                                    <td>{{ $item['sender']->displayName() }}</td>
                                    <td data-order="{{ $item['quantity'] }}">{{ format_quantity($item['quantity'], $item['lot']->unit) }}</td>
                                    <td><span class="badge badge-{{ $item['status_color'] }}">{{ $item['status_label'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Refuse modal -->
    <div class="modal fade" id="refuseModal" tabindex="-1" role="dialog" aria-labelledby="refuseModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form class="modal-content" method="POST" action="#">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="refuseModalLabel">{{ __('transfers.receptions.refuse_title') }}</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="{{ __('ui.close') }}">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="font-weight-bold js-action-label"></p>
                    <label for="rejection_reason">{{ __('transfers.receptions.refuse_reason') }}</label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="3" class="form-control" required minlength="5" maxlength="500"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">{{ __('ui.cancel') }}</button>
                    <button class="btn btn-danger" type="submit">{{ __('transfers.receptions.refuse') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection
