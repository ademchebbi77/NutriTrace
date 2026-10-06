@extends('layouts.public')

@section('title', __('public.how.title'))

@section('content')
    <!-- Header-->
    <header class="bg-dark py-5">
        <div class="container px-4 px-lg-5 my-4">
            <div class="text-center text-white">
                <h1 class="display-5 fw-bolder">{{ __('public.how.heading') }}</h1>
                <p class="lead fw-normal text-white-50 mb-0">{{ __('public.how.sub') }}</p>
            </div>
        </div>
    </header>

    <!-- Steps (Landing Page icons grid)-->
    <section class="features-icons bg-light text-center">
        <div class="container">
            <h2 class="fw-bolder mb-5">{{ __('public.how.steps_title') }}</h2>
            <div class="row justify-content-center">
                @foreach (__('public.how.steps') as $index => $step)
                    <div class="col-lg col-md-4 col-sm-6">
                        <div class="features-icons-item mx-auto mb-5 mb-lg-0">
                            <div class="features-icons-icon d-flex"><i class="{{ $step['icon'] }} m-auto text-primary" aria-hidden="true"></i></div>
                            <h3 class="h5">{{ $index + 1 }}. {{ $step['title'] }}</h3>
                            <p class="mb-0 small">{{ $step['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container px-4 px-lg-5">
            <div class="row gx-4 gx-lg-5">
                <div class="col-lg-6 mb-5">
                    <h2 class="fw-bolder">{{ __('public.how.footprint_title') }}</h2>
                    <p>{{ __('public.how.footprint_text') }}</p>
                    <div class="alert alert-warning" role="note">
                        <i class="bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('public.how.footprint_warning') }}
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">{{ __('public.how.indicator') }}</th>
                                    <th scope="col">{{ __('public.how.weight') }}</th>
                                    <th scope="col">{{ __('public.how.best') }}</th>
                                    <th scope="col">{{ __('public.how.worst') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['co2' => 'co2_per_kg', 'water' => 'water_per_kg', 'energy' => 'energy_per_kg', 'food_miles' => 'food_miles'] as $weight => $threshold)
                                    <tr>
                                        <th scope="row">{{ __('admin.settings.labels.'.$threshold) }}</th>
                                        <td>{{ round($footprint['weights'][$weight] * 100) }} %</td>
                                        <td>{{ $footprint['thresholds'][$threshold]['best'] }}</td>
                                        <td>{{ $footprint['thresholds'][$threshold]['worst'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h3 class="h5 fw-bolder mt-4">{{ __('public.how.grades_title') }}</h3>
                    <p>
                        @foreach ($footprint['grades'] as $grade => $minimum)
                            <span class="badge grade-badge grade-{{ strtolower($grade) }}">{{ $grade }}</span>
                            <span class="me-3">{{ __('public.how.grade_from', ['score' => $minimum]) }}</span>
                        @endforeach
                        <span class="badge grade-badge grade-e">E</span>
                        {{ __('public.how.grade_below', ['score' => $footprint['grades']['D']]) }}
                    </p>

                    <h3 class="h5 fw-bolder mt-4">{{ __('public.how.factors_title') }}</h3>
                    <ul class="list-group">
                        @foreach (App\Enums\TransportType::cases() as $type)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $type->label() }}</span>
                                <span>{{ $footprint['emission_factors']['transport'][$type->value] }} <span class="small text-muted">{{ __('public.how.factors_unit') }}</span></span>
                            </li>
                        @endforeach
                    </ul>

                    <h3 class="h5 fw-bolder mt-4">{{ __('public.how.sources_title') }}</h3>
                    <ul class="list-unstyled">
                        @foreach (App\Enums\DataSource::cases() as $source)
                            <li class="mb-2"><span class="badge source-badge source-{{ strtolower($source->value) }}">{{ $source->label() }}</span>
                                {{ \Illuminate\Support\Str::after(__('impacts.source_help.'.$source->value), ': ') }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="col-lg-6 mb-5">
                    <h2 class="fw-bolder">{{ __('public.how.trust_title') }}</h2>
                    <p>{{ __('public.how.trust_text') }}</p>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">{{ __('public.how.criterion') }}</th>
                                    <th scope="col">{{ __('public.how.points') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trust['weights'] as $key => $points)
                                    <tr>
                                        <th scope="row">{{ __('trust.components.'.$key) }}</th>
                                        <td>{{ $points }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h3 class="h5 fw-bolder mt-4">{{ __('public.how.penalties_title') }}</h3>
                    <ul class="list-group">
                        @foreach (collect($trust['penalties'])->except('max_total') as $key => $points)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ __('trust.penalties.'.$key) }}</span>
                                <span>{{ __('public.how.per_occurrence', ['points' => $points]) }}</span>
                            </li>
                        @endforeach
                        <li class="list-group-item list-group-item-light">{{ __('public.how.max_penalty', ['points' => $trust['penalties']['max_total']]) }}</li>
                    </ul>

                    <h3 class="h5 fw-bolder mt-4">{{ __('public.how.local_title') }}</h3>
                    <p>{{ __('public.how.local_text', ['radius' => $trust['local_radius_km']]) }}</p>

                    <h3 class="h5 fw-bolder mt-4">{{ __('public.how.chain_title') }}</h3>
                    <p class="mb-0">{{ __('public.how.chain_text') }}</p>
                </div>
            </div>
        </div>
    </section>
@endsection
