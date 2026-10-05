@extends('layouts.public')

@section('title', __('public.compare.title'))

@php
    // Highlight the best value of a row (lowest or highest) when lots differ.
    $best = function (string $row, bool $lowerIsBetter) use ($compared) {
        $values = $compared->map(fn ($insights) => match ($row) {
            'co2_per_kg' => $insights->co2PerKg(),
            'food_miles' => $insights->impact?->food_miles_km,
            'score' => $insights->impact?->score,
            'trust' => $insights->trust->score,
            'warnings' => $insights->warnings->whereIn('severity', ['high', 'medium'])->count(),
        })->filter(fn ($value) => $value !== null);

        if ($values->unique()->count() < 2) {
            return null;
        }

        return $lowerIsBetter ? $values->min() : $values->max();
    };
    $bestBadge = '<span class="badge bg-success ms-1">'.e(__('public.compare.best')).'</span>';
@endphp

@section('content')
    <!-- Header-->
    <header class="bg-dark py-5">
        <div class="container px-4 px-lg-5 my-4">
            <div class="text-center text-white">
                <h1 class="display-5 fw-bolder">{{ __('public.compare.heading') }}</h1>
                <p class="lead fw-normal text-white-50 mb-0">{{ __('public.compare.sub') }}</p>
            </div>
        </div>
    </header>

    <section class="py-5">
        <div class="container px-4 px-lg-5">
            <form method="GET" action="{{ route('compare') }}" class="card card-body bg-light border-0 mb-4">
                <div class="row g-3 align-items-end">
                    @for ($i = 0; $i < App\Http\Controllers\PublicSite\CompareController::MAX_LOTS; $i++)
                        <div class="col-md-3">
                            <label class="form-label" for="lot_{{ $i }}">{{ __('public.compare.lot_n', ['n' => $i + 1]) }}</label>
                            <input class="form-control" type="text" name="lots[]" id="lot_{{ $i }}" list="lotSuggestions"
                                value="{{ $numbers[$i] ?? '' }}" placeholder="{{ __('public.compare.placeholder') }}" autocomplete="off" />
                        </div>
                    @endfor
                    <div class="col-md-3 d-grid">
                        <button class="btn btn-dark" type="submit"><i class="bi-layout-split me-1" aria-hidden="true"></i>{{ __('public.compare.submit') }}</button>
                    </div>
                </div>
                <datalist id="lotSuggestions">
                    @foreach ($suggestions as $suggestion)
                        <option value="{{ $suggestion->lot_number }}">{{ $suggestion->product->name }}</option>
                    @endforeach
                </datalist>
            </form>

            @if ($notFound->isNotEmpty())
                <div class="alert alert-warning" role="alert">{{ __('public.compare.not_found', ['numbers' => $notFound->join(', ')]) }}</div>
            @endif

            @if ($compared->count() < 2)
                <div class="alert alert-secondary" role="status">{{ __('public.compare.need_two') }}</div>

                <h2 class="h5 fw-bolder mt-4">{{ __('public.compare.suggestions') }}</h2>
                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3">
                    @foreach ($suggestions->take(12) as $suggestion)
                        <div class="col">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="fw-bolder">{{ $suggestion->lot_number }}</div>
                                    <div class="small text-muted">{{ $suggestion->product->name }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('public.compare.criteria') }}</th>
                                @foreach ($compared as $insights)
                                    <th scope="col">
                                        {{ $insights->lot->lot_number }}
                                        <a class="btn btn-sm btn-outline-dark ms-2" href="{{ $insights->lot->publicUrl() }}">{{ __('public.compare.see') }}</a>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.product') }}</th>
                                @foreach ($compared as $insights)
                                    <td class="fw-bolder">{{ $insights->lot->product->name }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.origin') }}</th>
                                @foreach ($compared as $insights)
                                    <td>{{ $insights->lot->product->origin ?: '—' }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.grade') }}</th>
                                @foreach ($compared as $insights)
                                    <td>
                                        @if ($insights->impact?->grade)
                                            <span class="badge grade-badge grade-{{ strtolower($insights->impact->grade) }}">{{ $insights->impact->grade }}</span>
                                            {{ $insights->impact->score }} / 100
                                            @if ($insights->impact->score === $best('score', false)) {!! $bestBadge !!} @endif
                                        @else
                                            <span class="text-muted">{{ __('impacts.not_available') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.co2_per_kg') }}</th>
                                @foreach ($compared as $insights)
                                    <td>
                                        @if ($insights->co2PerKg() !== null)
                                            {{ number_format($insights->co2PerKg(), 2, ',', ' ') }} kg CO₂e
                                            <span class="badge source-badge source-{{ strtolower($insights->impact->source('co2_kg')->value) }}">{{ $insights->impact->source('co2_kg')->label() }}</span>
                                            @if ($insights->co2PerKg() === $best('co2_per_kg', true)) {!! $bestBadge !!} @endif
                                        @else
                                            <span class="text-muted">{{ __('public.trace.not_declared') }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            @foreach (['water' => 'water_l', 'energy' => 'energy_kwh'] as $row => $indicator)
                                <tr>
                                    <th scope="row">{{ __('public.compare.rows.'.$row) }}</th>
                                    @foreach ($compared as $insights)
                                        <td>
                                            @if ($insights->impact?->{$indicator} !== null)
                                                {{ number_format($insights->impact->{$indicator}, 0, ',', ' ') }} {{ __('impacts.units.'.$indicator) }}
                                                <span class="badge source-badge source-{{ strtolower($insights->impact->source($indicator)->value) }}">{{ $insights->impact->source($indicator)->label() }}</span>
                                            @else
                                                <span class="text-muted">{{ __('public.trace.not_declared') }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.food_miles') }}</th>
                                @foreach ($compared as $insights)
                                    <td>
                                        {{ number_format($insights->impact?->food_miles_km ?? 0, 0, ',', ' ') }} km
                                        @if ($insights->impact && $insights->impact->food_miles_km === $best('food_miles', true)) {!! $bestBadge !!} @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.local') }}</th>
                                @foreach ($compared as $insights)
                                    <td>
                                        @if ($insights->local['is_local'] === true)
                                            <span class="badge bg-success">{{ __('trust.local.yes') }}</span>
                                        @elseif ($insights->local['is_local'] === false)
                                            <span class="badge bg-secondary">{{ __('trust.local.no') }}</span>
                                        @else
                                            <span class="text-muted">{{ __('trust.local.unknown') }}</span>
                                        @endif
                                        @if ($insights->local['distance_km'] !== null)
                                            <span class="small text-muted">({{ number_format($insights->local['distance_km'], 0, ',', ' ') }} km)</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.trust') }}</th>
                                @foreach ($compared as $insights)
                                    <td>
                                        <span class="badge bg-{{ $insights->trust->color() }}">{{ $insights->trust->score }} / 100</span>
                                        {{ $insights->trust->level() }}
                                        @if ($insights->trust->score === $best('trust', false)) {!! $bestBadge !!} @endif
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.certifications') }}</th>
                                @foreach ($compared as $insights)
                                    <td>
                                        @forelse ($insights->validCertifications() as $certification)
                                            <span class="badge bg-success"><i class="bi-patch-check me-1" aria-hidden="true"></i>{{ $certification->name }}</span>
                                        @empty
                                            <span class="text-muted">{{ __('public.compare.none') }}</span>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.warnings') }}</th>
                                @foreach ($compared as $insights)
                                    <td>
                                        @forelse ($insights->warnings->whereIn('severity', ['high', 'medium']) as $warning)
                                            <div class="small"><i class="bi-exclamation-triangle-fill text-{{ $warning['severity'] === 'high' ? 'danger' : 'warning' }}" aria-hidden="true"></i> {{ $warning['message'] }}</div>
                                        @empty
                                            <span class="text-success"><i class="bi-check-circle-fill" aria-hidden="true"></i> {{ __('public.compare.none') }}</span>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row">{{ __('public.compare.rows.chain') }}</th>
                                @foreach ($compared as $insights)
                                    <td>
                                        <span class="badge bg-{{ $insights->chain->valid ? 'success' : 'danger' }}">
                                            {{ $insights->chain->valid ? __('public.compare.intact') : __('public.compare.broken') }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
