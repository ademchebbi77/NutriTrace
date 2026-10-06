@extends('layouts.layout')

@section('title', __('menu.history'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800">{{ __('menu.history') }}</h1>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">{{ __('dashboards.consumer.history') }}</h6>
        </div>
        <div class="card-body">
            @if ($lots->isEmpty())
                <p class="mb-0 text-gray-600">{{ __('dashboards.consumer.recent_empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0" data-order="[]">
                        <thead>
                            <tr>
                                <th>{{ __('reviews.fields.date') }}</th>
                                <th>{{ __('lots.fields.product') }}</th>
                                <th>{{ __('lots.fields.lot_number') }}</th>
                                <th>{{ __('lots.fields.grade') }}</th>
                                <th>{{ __('trust.title') }}</th>
                                <th class="no-sort">{{ __('ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $lot)
                                @php $viewedAt = \Illuminate\Support\Carbon::parse($lot->pivot->viewed_at); @endphp
                                <tr>
                                    <td data-order="{{ $viewedAt->timestamp }}">{{ $viewedAt->format('d/m/Y H:i') }}</td>
                                    <td class="font-weight-bold">{{ $lot->product->name }}</td>
                                    <td>{{ $lot->lot_number }}</td>
                                    <td>
                                        @if ($lot->environmentalImpact?->grade)
                                            <span class="badge badge-{{ $lot->environmentalImpact->gradeColor() }} px-2 py-1">{{ $lot->environmentalImpact->grade }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td data-order="{{ $lot->trust_score ?? -1 }}">{{ $lot->trust_score !== null ? $lot->trust_score.' / 100' : '—' }}</td>
                                    <td>
                                        <a href="{{ $lot->publicUrl() }}" class="btn btn-info btn-circle btn-sm"
                                            title="{{ __('ui.view') }}" aria-label="{{ __('ui.view') }} {{ $lot->lot_number }}">
                                            <i class="fas fa-eye"></i></a>
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
