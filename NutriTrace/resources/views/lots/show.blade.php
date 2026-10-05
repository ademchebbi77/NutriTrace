@extends('layouts.layout')

@section('title', __('lots.show_title', ['number' => $lot->lot_number]))

@php
    $impact = $lot->environmentalImpact;
    $user = auth()->user();
@endphp

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ $lot->lot_number }}
            <span class="badge badge-{{ $lot->status->color() }} align-middle">{{ $lot->status->label() }}</span>
            @if ($lot->isExpired())
                <span class="badge badge-danger align-middle">{{ __('lots.expired') }}</span>
            @endif
        </h1>
        <div class="mt-2 mt-sm-0">
            <a href="{{ area_route('lots.index') }}" class="btn btn-sm btn-light shadow-sm">
                <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
            @if ($lot->product->isPubliclyVisible())
                <a href="{{ $lot->publicUrl() }}" class="btn btn-sm btn-light shadow-sm" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt fa-sm"></i> {{ __('lots.public_page') }}</a>
            @endif
            @if (area_has_route('labels.show'))
                <a href="{{ area_route('labels.show', $lot) }}" class="btn btn-sm btn-light shadow-sm">
                    <i class="fas fa-qrcode fa-sm"></i> {{ __('lots.label') }}</a>
            @endif
            @can('update', $lot)
                <a href="{{ area_route('lots.edit', $lot) }}" class="btn btn-sm btn-light shadow-sm">
                    <i class="fas fa-pen fa-sm"></i> {{ __('ui.edit') }}</a>
            @endcan
            @can('declareImpact', $lot)
                <a href="{{ area_route('impacts.edit', $lot) }}" class="btn btn-sm btn-light shadow-sm">
                    <i class="fas fa-leaf fa-sm"></i> {{ __('lots.declare_impact') }}</a>
            @endcan
            @can('send', $lot)
                <a href="{{ area_route('transfers.create', $lot) }}" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-paper-plane fa-sm text-white-50"></i> {{ __('transfers.send') }}</a>
            @endcan
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        @foreach ([
            ['label' => __('lots.fields.quantity'), 'value' => $lot->formattedQuantity().' / '.$lot->formattedQuantity($lot->initial_quantity), 'icon' => 'fa-weight-hanging', 'color' => 'primary'],
            ['label' => __('lots.fields.holder'), 'value' => $lot->currentHolder->displayName(), 'icon' => 'fa-hand-holding', 'color' => 'info'],
            ['label' => __('lots.fields.grade'), 'value' => $impact?->grade ? $impact->grade.' ('.$impact->score.' / 100)' : __('trust.not_available'), 'icon' => 'fa-leaf', 'color' => $impact?->gradeColor() ?? 'secondary'],
            ['label' => __('trust.title'), 'value' => $trust->score.' / 100', 'icon' => 'fa-shield-alt', 'color' => $trust->color()],
        ] as $stat)
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-{{ $stat['color'] }} shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-{{ $stat['color'] }} text-uppercase mb-1">{{ $stat['label'] }}</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stat['value'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas {{ $stat['icon'] }} fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('lots.journey') }}</h6>
                    @if ($chain->valid)
                        <span class="badge badge-success"><i class="fas fa-lock fa-sm"></i> {{ __('lots.chain_valid', ['count' => $chain->events]) }}</span>
                    @else
                        <span class="badge badge-danger"><i class="fas fa-unlink fa-sm"></i> {{ __('lots.chain_broken') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @include('partials.timeline', ['events' => $journey->allEvents(), 'currentLot' => $lot])
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('lots.information') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ __('lots.fields.product') }}</dt>
                        <dd class="col-sm-7">{{ $lot->product->name }} ({{ $lot->product->category->name }})</dd>

                        <dt class="col-sm-5">{{ __('lots.fields.production_date') }}</dt>
                        <dd class="col-sm-7">{{ $lot->production_date->format('d/m/Y') }}</dd>

                        <dt class="col-sm-5">{{ __('lots.fields.expiration_date') }}</dt>
                        <dd class="col-sm-7">{{ $lot->expiration_date?->format('d/m/Y') ?? __('lots.fields.none') }}</dd>

                        <dt class="col-sm-5">{{ __('lots.origin_card') }}</dt>
                        <dd class="col-sm-7 mb-0">
                            @if ($lot->production)
                                {{ $lot->production->producer->displayName() }}, {{ $lot->production->location_city }}
                                <br><span class="small text-gray-600">{{ $lot->production->production_method->label() }}</span>
                            @else
                                {{ __('lots.from_transformation') }}
                                @foreach ($journey->sources as $source)
                                    <br><span class="small">
                                        @if ($source['lot']->isVisibleTo($user))
                                            <a href="{{ area_route('lots.show', $source['lot']) }}">{{ $source['lot']->lot_number }}</a>
                                        @else
                                            {{ $source['lot']->lot_number }}
                                        @endif
                                        &middot; {{ $source['lot']->product->name }}
                                        ({{ format_quantity($source['quantity_used'], $source['lot']->unit) }})</span>
                                @endforeach
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('warnings.title') }}</h6>
                </div>
                <div class="card-body">
                    @forelse ($warnings as $warning)
                        <div class="d-flex @if (! $loop->last) mb-2 @endif">
                            <span class="badge badge-{{ ['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'][$warning['severity']] }} align-self-start mr-2">
                                {{ __('warnings.severity.'.$warning['severity']) }}</span>
                            <span class="small">{{ $warning['message'] }}
                                @if ($warning['related'])
                                    <span class="text-gray-600">({{ $warning['related'] }})</span>
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="mb-0 text-success"><i class="fas fa-check-circle mr-1"></i>{{ __('warnings.none') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('trust.why') }}</h6>
                </div>
                <div class="card-body">
                    @include('partials.trust-breakdown', ['trust' => $trust])
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('certifications.title') }}</h6>
                </div>
                <div class="card-body">
                    @forelse ($lot->allCertifications() as $certification)
                        <div class="d-flex justify-content-between align-items-start @if (! $loop->last) border-bottom pb-2 mb-2 @endif">
                            <div>
                                <span class="font-weight-bold">{{ $certification->name }}</span>
                                <div class="small text-gray-600">{{ $certification->issuing_organization }}
                                    @if ($certification->certificate_number) &middot; {{ $certification->certificate_number }} @endif
                                </div>
                            </div>
                            <span class="badge badge-{{ $certification->isValid() ? 'success' : $certification->status->color() }}">{{ $certification->status->label() }}</span>
                        </div>
                    @empty
                        <p class="mb-0 text-gray-600">{{ __('certifications.none') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

@endsection
