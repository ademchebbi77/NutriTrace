@extends('layouts.public')

@section('title', __('public.trace.title', ['number' => $lot->lot_number]))
@section('description', $lot->product->name.' - '.__('public.trace.lot', ['number' => $lot->lot_number]))

@php
    $product = $lot->product;
    $impact = $insights->impact;
    $trust = $insights->trust;
    $points = $insights->journey->points();
    $unitLabel = $lot->unit === App\Enums\Unit::LITRE ? 'L' : 'kg';

    $stageValues = collect([
        'production' => $insights->stages['production_co2'],
        'transformation' => $insights->stages['transformation_co2'],
        'transport' => $insights->stages['transport_co2'],
        'packaging' => $impact?->packaging_co2_kg,
    ])->map(fn ($value) => round($value ?? 0, 2));

    $severityStyles = ['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'];
    $localBadge = match ($insights->local['is_local']) {
        true => 'success',
        false => 'secondary',
        default => 'light text-dark border',
    };
    $canReview = auth()->user()?->can('create', App\Models\Review::class) ?? false;
    $canReport = auth()->user()?->can('create', App\Models\Report::class) ?? false;
@endphp

@section('content')
    @if ($insights->underInvestigation)
        <div class="alert alert-warning rounded-0 mb-0 text-center" role="alert">
            <i class="bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>{{ __('reports.under_investigation') }}
        </div>
    @endif

    <!-- Product section-->
    <section class="py-5">
        <div class="container px-4 px-lg-5 my-4">
            <div class="row gx-4 gx-lg-5 align-items-center">
                <div class="col-md-5">
                    @if ($product->imageUrl())
                        <img class="card-img-top mb-5 mb-md-0 rounded" src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" />
                    @else
                        <div class="bg-light rounded d-flex align-items-center justify-content-center mb-5 mb-md-0" style="aspect-ratio: 3 / 2;">
                            <i class="bi-image fs-1 text-muted" aria-hidden="true"></i>
                        </div>
                    @endif
                </div>
                <div class="col-md-7">
                    <div class="small mb-1">{{ __('public.trace.lot', ['number' => $lot->lot_number]) }} &middot; {{ $product->category->name }}</div>
                    <h1 class="display-5 fw-bolder">{{ $product->name }}</h1>
                    <div class="fs-5 mb-3">
                        @if ($impact?->grade)
                            <span class="badge grade-badge grade-{{ strtolower($impact->grade) }}">{{ __('public.catalog.grade_badge', ['grade' => $impact->grade]) }}</span>
                        @endif
                        <span class="badge bg-{{ $trust->color() }}"><i class="bi-shield-check me-1" aria-hidden="true"></i>{{ __('public.catalog.trust_badge', ['score' => $trust->score]) }}</span>
                        @if ($insights->local['is_local'] === true)
                            <span class="badge bg-success"><i class="bi-geo-alt me-1" aria-hidden="true"></i>{{ __('trust.local.yes') }}</span>
                        @endif
                        @foreach ($insights->validCertifications() as $certification)
                            <span class="badge bg-success"><i class="bi-patch-check me-1" aria-hidden="true"></i>{{ $certification->type->label() }}</span>
                        @endforeach
                    </div>
                    <p class="lead">{{ $product->description }}</p>
                    <dl class="row mb-4">
                        <dt class="col-sm-5">{{ __('public.trace.facts.origin') }}</dt>
                        <dd class="col-sm-7">{{ $product->origin ?: '—' }}</dd>
                        <dt class="col-sm-5">{{ __('public.trace.facts.produced') }}</dt>
                        <dd class="col-sm-7">{{ $lot->production_date->translatedFormat('j F Y') }}</dd>
                        <dt class="col-sm-5">{{ __('public.trace.facts.expiration') }}</dt>
                        <dd class="col-sm-7">
                            {{ $lot->expiration_date?->translatedFormat('j F Y') ?? '—' }}
                            @if ($lot->isExpired())
                                <span class="badge bg-danger">{{ __('public.trace.expired') }}</span>
                            @endif
                        </dd>
                        <dt class="col-sm-5">{{ __('public.trace.facts.quantity') }}</dt>
                        <dd class="col-sm-7">{{ $lot->formattedQuantity($lot->initial_quantity) }}</dd>
                        <dt class="col-sm-5">{{ __('public.trace.facts.status') }}</dt>
                        <dd class="col-sm-7">{{ $lot->status->label() }}</dd>
                        @if ($insights->journey->isTransformed())
                            <dt class="col-sm-5">{{ __('public.trace.facts.made_from') }}</dt>
                            <dd class="col-sm-7 mb-0">
                                @foreach ($insights->journey->sources as $source)
                                    <div>
                                        @if ($source['lot']->product->isPubliclyVisible())
                                            <a href="{{ $source['lot']->publicUrl() }}">{{ $source['lot']->product->name }}</a>
                                        @else
                                            {{ $source['lot']->product->name }}
                                        @endif
                                        ({{ format_quantity($source['quantity_used'], $source['lot']->unit) }}, {{ $source['lot']->lot_number }})
                                    </div>
                                @endforeach
                            </dd>
                        @endif
                    </dl>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn btn-outline-dark flex-shrink-0" href="{{ route('compare', ['lots' => [$lot->lot_number]]) }}">
                            <i class="bi-layout-split me-1" aria-hidden="true"></i>{{ __('public.trace.compare') }}</a>
                        @if ($canReport)
                            <button class="btn btn-outline-danger flex-shrink-0" type="button" data-bs-toggle="modal" data-bs-target="#reportModal">
                                <i class="bi-flag me-1" aria-hidden="true"></i>{{ __('reports.button') }}</button>
                        @elseif (! auth()->check())
                            <a class="btn btn-outline-danger flex-shrink-0" href="{{ route('login') }}" title="{{ __('reports.login_to_report') }}">
                                <i class="bi-flag me-1" aria-hidden="true"></i>{{ __('reports.button') }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Journey: timeline and map-->
    <section class="py-5 bg-light" id="parcours">
        <div class="container px-4 px-lg-5">
            <h2 class="fw-bolder mb-1">{{ __('public.trace.journey') }}</h2>
            <p class="text-muted mb-4">{{ __('public.trace.journey_sub') }}</p>
            <div class="row gx-4 gx-lg-5">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <ol class="timeline list-unstyled mb-0">
                        @foreach ($insights->journey->allEvents() as $event)
                            <li class="timeline-item">
                                <span class="timeline-marker bg-{{ $event->event_type->color() }}" aria-hidden="true">
                                    <i class="{{ $event->event_type->publicIcon() }}"></i>
                                </span>
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body py-3">
                                        <div class="d-flex justify-content-between flex-wrap">
                                            <span class="fw-bolder">{{ $event->event_type->label() }}</span>
                                            <time class="small text-muted" datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->translatedFormat('j M Y, H:i') }}</time>
                                        </div>
                                        <div>{{ $event->description }}</div>
                                        <div class="small text-muted">
                                            @if ($event->location)
                                                <i class="bi-geo-alt" aria-hidden="true"></i> {{ $event->location }}
                                            @endif
                                            @if ($event->actorName())
                                                &middot; {{ $event->actorName() }}
                                                @if ($event->actor->organization->is_verified)
                                                    <i class="bi-patch-check-fill text-success" title="{{ __('public.product.verified_org') }}"></i>
                                                    <span class="visually-hidden">{{ __('public.product.verified_org') }}</span>
                                                @endif
                                            @endif
                                        </div>
                                        @unless ($event->lot->is($lot))
                                            <span class="badge bg-light text-dark border mt-1">{{ __('public.trace.source_lot', ['number' => $event->lot->lot_number]) }}</span>
                                        @endunless
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
                <div class="col-lg-6">
                    <h3 class="h5 fw-bolder">{{ __('public.trace.map') }}</h3>
                    @if (count($points) > 0)
                        <div id="journeyMap" class="rounded shadow-sm" style="height: 24rem;" role="img" aria-label="{{ __('public.trace.map') }}"></div>
                        <ol class="small text-muted mt-2 ps-3">
                            @foreach ($points as $point)
                                <li>{{ $point['label'] }} ({{ $point['type'] }})</li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-muted">{{ __('public.trace.map_empty') }}</p>
                    @endif

                    <div class="alert alert-{{ $insights->chain->valid ? 'success' : 'danger' }} mt-3 mb-0" role="status">
                        <i class="bi-{{ $insights->chain->valid ? 'lock-fill' : 'unlock-fill' }} me-1" aria-hidden="true"></i>
                        <strong>{{ $insights->chain->valid ? __('public.trace.chain_valid', ['count' => $insights->chain->events]) : __('public.trace.chain_broken') }}</strong>
                        <div class="small">{{ __('public.trace.chain_help') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Environmental card-->
    <section class="py-5" id="empreinte">
        <div class="container px-4 px-lg-5">
            <h2 class="fw-bolder mb-1">{{ __('public.trace.environment') }}</h2>
            <p class="text-muted mb-4">{{ __('public.trace.environment_sub') }}</p>
            @if (! $impact || ($impact->co2_kg === null && $impact->water_l === null && $impact->energy_kwh === null))
                <div class="alert alert-secondary" role="status">{{ __('public.trace.environment_empty') }}</div>
            @else
                <div class="row gx-4 gx-lg-5">
                    <div class="col-lg-4 mb-4 mb-lg-0">
                        <div class="card h-100 text-center">
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="text-muted">{{ __('public.trace.grade') }}</div>
                                @if ($impact->grade)
                                    <div class="grade-letter grade-{{ strtolower($impact->grade) }} mx-auto my-3">{{ $impact->grade }}</div>
                                    <div class="fs-5 fw-bolder">{{ $impact->score }} / 100</div>
                                @else
                                    <div class="fs-5 my-3">{{ __('impacts.not_available') }}</div>
                                @endif
                                <div class="small text-muted mt-2">{{ __('public.trace.grade_help') }}</div>
                                @if ($insights->co2PerKg() !== null)
                                    <div class="mt-3">{{ __('public.trace.per_kg', ['value' => number_format($insights->co2PerKg(), 2, ',', ' '), 'unit' => $unitLabel]) }}</div>
                                @endif
                                <div class="mt-3">
                                    <span class="badge bg-{{ $localBadge }}">
                                        @if ($insights->local['is_local'] === true)
                                            {{ __('trust.local.yes') }}
                                        @elseif ($insights->local['is_local'] === false)
                                            {{ __('trust.local.no') }}
                                        @else
                                            {{ __('trust.local.unknown') }}
                                        @endif
                                    </span>
                                    <div class="small text-muted mt-1">
                                        @if ($insights->local['is_local'] === null)
                                            {{ __('trust.local.explain_unknown') }}
                                        @else
                                            {{ __($insights->local['is_local'] ? 'trust.local.explain_yes' : 'trust.local.explain_no', [
                                                'km' => number_format($insights->local['distance_km'], 0, ',', ' '),
                                                'radius' => number_format($insights->local['radius_km'], 0, ',', ' '),
                                            ]) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4 mb-lg-0">
                        <div class="card h-100">
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    @foreach (App\Models\EnvironmentalImpact::INDICATORS as $indicator)
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span>{{ __('impacts.indicators.'.$indicator) }}</span>
                                            <span class="text-end">
                                                @if ($impact->{$indicator} !== null)
                                                    <strong>{{ number_format($impact->{$indicator}, 1, ',', ' ') }} {{ __('impacts.units.'.$indicator) }}</strong>
                                                    <br><span class="badge source-badge source-{{ strtolower($impact->source($indicator)->value) }}">{{ $impact->source($indicator)->label() }}</span>
                                                @else
                                                    <span class="text-muted">{{ __('public.trace.not_declared') }}</span>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span>{{ __('impacts.indicators.food_miles_km') }}</span>
                                        <span class="text-end">
                                            <strong>{{ number_format($impact->food_miles_km ?? 0, 0, ',', ' ') }} km</strong>
                                            <br><span class="badge source-badge source-calculated">{{ App\Enums\DataSource::CALCULATED->label() }}</span>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h3 class="h6 fw-bolder">{{ __('public.trace.chart_title') }}</h3>
                                @if ($stageValues->sum() > 0)
                                    <div style="height: 15rem;">
                                        <canvas id="stagesChart" role="img" aria-label="{{ __('public.trace.chart_title') }} : @foreach ($stageValues as $stage => $value){{ __('impacts.stages.'.$stage) }} {{ number_format($value, 1, ',', ' ') }} kg CO₂e. @endforeach"></canvas>
                                    </div>
                                @else
                                    <p class="text-muted">{{ __('impacts.no_stage_data') }}</p>
                                @endif
                                <h3 class="h6 fw-bolder mt-3">{{ __('public.trace.sources_legend') }}</h3>
                                <ul class="list-unstyled small mb-0">
                                    @foreach (App\Enums\DataSource::cases() as $source)
                                        <li class="mb-1"><span class="badge source-badge source-{{ strtolower($source->value) }}">{{ $source->label() }}</span>
                                            {{ \Illuminate\Support\Str::after(__('impacts.source_help.'.$source->value), ': ') }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <!-- Certifications-->
    <section class="py-5 bg-light" id="certifications">
        <div class="container px-4 px-lg-5">
            <h2 class="fw-bolder mb-1">{{ __('public.trace.certifications') }}</h2>
            <p class="text-muted mb-4">{{ __('public.trace.certifications_sub') }}</p>
            @if ($insights->certifications->isEmpty())
                <p class="text-muted mb-0">{{ __('public.trace.certifications_empty') }}</p>
            @else
                <div class="row gx-4 row-cols-1 row-cols-md-2 row-cols-xl-3">
                    @foreach ($insights->certifications as $certification)
                        <div class="col mb-4">
                            <div class="card h-100 {{ $certification->isValid() ? 'border-success' : '' }}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h3 class="h6 fw-bolder mb-0">{{ $certification->name }}</h3>
                                        @if ($certification->isValid())
                                            <span class="badge bg-success"><i class="bi-patch-check-fill me-1" aria-hidden="true"></i>{{ __('public.trace.cert_valid') }}</span>
                                        @else
                                            <span class="badge bg-{{ $certification->status->color() === 'warning' ? 'warning text-dark' : $certification->status->color() }}">
                                                {{ $certification->isPastExpiration() ? App\Enums\CertificationStatus::EXPIRED->label() : $certification->status->label() }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted mb-2">{{ $certification->type->label() }}</div>
                                    <dl class="small mb-0">
                                        <dt>{{ __('public.trace.cert_issuer') }}</dt>
                                        <dd>{{ $certification->issuing_organization }}</dd>
                                        <dt>{{ __('public.trace.cert_number') }}</dt>
                                        <dd>{{ $certification->certificate_number ?: '—' }}</dd>
                                        <dt>{{ __('public.trace.cert_validity') }}</dt>
                                        <dd class="mb-0">
                                            {{ __('public.trace.cert_since', ['date' => $certification->issue_date->format('d/m/Y')]) }}
                                            @if ($certification->expiration_date)
                                                {{ __('public.trace.cert_until', ['date' => $certification->expiration_date->format('d/m/Y')]) }}
                                            @endif
                                        </dd>
                                    </dl>
                                </div>
                                @if ($certification->isValid() && $certification->document_path)
                                    <div class="card-footer bg-transparent">
                                        <a href="{{ route('trace.proof', [$lot->public_token, $certification]) }}" target="_blank" rel="noopener" class="small">
                                            <i class="bi-file-earmark-text me-1" aria-hidden="true"></i>{{ __('public.trace.cert_proof') }}</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- Transparency score and greenwashing warnings-->
    <section class="py-5" id="transparence">
        <div class="container px-4 px-lg-5">
            <h2 class="fw-bolder mb-4">{{ __('public.trace.transparency') }}</h2>
            <div class="row gx-4 gx-lg-5">
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="trust-score border-{{ $trust->color() }} text-{{ $trust->color() }} me-3">{{ $trust->score }}</div>
                                <div>
                                    <div class="fw-bolder">{{ __('trust.title') }} <span class="text-muted fw-normal">{{ __('trust.out_of') }}</span></div>
                                    <div class="text-{{ $trust->color() }}">{{ $trust->level() }}</div>
                                </div>
                            </div>
                            <p class="small text-muted">{{ __('trust.intro') }}</p>
                            <button class="btn btn-outline-dark btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#trustBreakdown" aria-expanded="true" aria-controls="trustBreakdown">
                                <i class="bi-question-circle me-1" aria-hidden="true"></i>{{ __('trust.why') }}
                            </button>
                            <div class="collapse show mt-3" id="trustBreakdown">
                                @include('partials.trust-breakdown', ['trust' => $trust])
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h5 fw-bolder">{{ __('warnings.title') }}</h3>
                            @forelse ($insights->warnings as $warning)
                                <div class="alert alert-{{ $severityStyles[$warning['severity']] }} d-flex py-2 mb-2" role="note">
                                    <i class="bi-{{ $warning['severity'] === 'low' ? 'info-circle' : 'exclamation-triangle' }}-fill me-2" aria-hidden="true"></i>
                                    <div>
                                        <strong>{{ __('warnings.severity.'.$warning['severity']) }} :</strong> {{ $warning['message'] }}
                                        @if ($warning['related'])
                                            <div class="small">{{ __('warnings.related') }} {{ $warning['related'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="alert alert-success mb-0" role="status">
                                    <i class="bi-check-circle-fill me-1" aria-hidden="true"></i>{{ __('warnings.none') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Reviews-->
    <section class="py-5 bg-light" id="avis">
        <div class="container px-4 px-lg-5">
            <h2 class="fw-bolder mb-4">{{ __('reviews.title') }}
                <span class="fs-6 fw-normal text-muted">({{ trans_choice('reviews.count', $reviews->count(), ['count' => $reviews->count()]) }})</span></h2>
            <div class="row gx-4 gx-lg-5">
                <div class="col-lg-7 mb-4 mb-lg-0">
                    @forelse ($reviews as $review)
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bolder">{{ \Illuminate\Support\Str::before($review->user->name, ' ') }}</span>
                                    <span class="small text-muted">{{ $review->updated_at->format('d/m/Y') }}</span>
                                </div>
                                <div class="text-warning" aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                                <span class="visually-hidden">{{ __('reviews.stars', ['count' => $review->rating]) }}</span>
                                @if ($review->comment)
                                    <p class="mb-0 mt-1">{{ $review->comment }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">{{ __('reviews.none') }}</p>
                    @endforelse
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="h5 fw-bolder">{{ $ownReview ? __('reviews.form_title_edit') : __('reviews.form_title') }}</h3>
                            @if ($canReview)
                                <form method="POST" action="{{ route('trace.review', $lot->public_token) }}" novalidate>
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label" for="rating">{{ __('reviews.fields.rating') }}</label>
                                        <select class="form-select @error('rating', 'review') is-invalid @enderror" name="rating" id="rating" required>
                                            @foreach ([5, 4, 3, 2, 1] as $rating)
                                                <option value="{{ $rating }}" @selected((int) old('rating', $ownReview?->rating ?? 5) === $rating)>{{ str_repeat('★', $rating) }} {{ __('reviews.stars', ['count' => $rating]) }}</option>
                                            @endforeach
                                        </select>
                                        @error('rating', 'review')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="comment">{{ __('reviews.fields.comment') }}</label>
                                        <textarea class="form-control @error('comment', 'review') is-invalid @enderror" name="comment" id="comment" rows="3" maxlength="1000" aria-describedby="reviewHelp">{{ old('comment', $ownReview?->comment) }}</textarea>
                                        <div id="reviewHelp" class="form-text">{{ __('reviews.one_per_product') }}</div>
                                        @error('comment', 'review')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <button class="btn btn-dark" type="submit">{{ __('reviews.submit') }}</button>
                                </form>
                            @elseif (auth()->check())
                                <p class="text-muted mb-0">{{ __('reviews.consumers_only') }}</p>
                            @else
                                <p class="text-muted">{{ __('reviews.login_to_review') }}</p>
                                <a class="btn btn-outline-dark" href="{{ route('login') }}">{{ __('public.trace.login') }}</a>
                            @endif
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-body text-center">
                            <h3 class="h6 fw-bolder">{{ __('public.trace.qr') }}</h3>
                            <div role="img" aria-label="{{ __('public.trace.qr') }}">{!! $qr !!}</div>
                            <a class="small d-block mt-2" href="{{ route('api.v1.trace.show', $lot->public_token) }}" rel="nofollow">
                                <i class="bi-braces me-1" aria-hidden="true"></i>{{ __('public.trace.api') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Related items section: other lots of the product-->
    @if ($otherLots->isNotEmpty())
        <section class="py-5">
            <div class="container px-4 px-lg-5 mt-3">
                <h2 class="fw-bolder mb-4">{{ __('public.trace.other_lots') }}</h2>
                <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                    @foreach ($otherLots as $other)
                        <div class="col mb-5">
                            <div class="card h-100">
                                @if ($other->environmentalImpact?->grade)
                                    <div class="badge grade-badge grade-{{ strtolower($other->environmentalImpact->grade) }} position-absolute" style="top: 0.5rem; right: 0.5rem">
                                        {{ __('public.catalog.grade_badge', ['grade' => $other->environmentalImpact->grade]) }}</div>
                                @endif
                                <div class="card-body p-4">
                                    <div class="text-center">
                                        <h3 class="h5 fw-bolder">{{ $other->lot_number }}</h3>
                                        {{ $other->production_date->format('d/m/Y') }}
                                        <div class="small text-muted">{{ $other->status->label() }}</div>
                                    </div>
                                </div>
                                <div class="card-footer p-4 pt-0 border-top-0 bg-transparent">
                                    <div class="text-center"><a class="btn btn-outline-dark mt-auto stretched-link" href="{{ $other->publicUrl() }}">{{ __('public.product.open') }}</a></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- Report modal-->
    @if ($canReport)
        <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('trace.report', $lot->public_token) }}" novalidate>
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="reportModalLabel">{{ __('reports.modal_title') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('ui.close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">{{ __('reports.modal_intro') }}</p>
                        <div class="mb-3">
                            <label class="form-label" for="report_target">{{ __('reports.fields.target') }}</label>
                            <select class="form-select @error('target', 'report') is-invalid @enderror" name="target" id="report_target" required>
                                <option value="lot" @selected(old('target') === 'lot')>{{ __('reports.target_options.lot', ['name' => $lot->lot_number]) }}</option>
                                <option value="product" @selected(old('target') === 'product')>{{ __('reports.target_options.product', ['name' => $product->name]) }}</option>
                                @if ($impact)
                                    <option value="impact" @selected(old('target') === 'impact')>{{ __('reports.target_options.impact') }}</option>
                                @endif
                                @foreach ($insights->certifications as $certification)
                                    <option value="certification:{{ $certification->id }}" @selected(old('target') === 'certification:'.$certification->id)>{{ __('reports.target_options.certification', ['name' => $certification->name]) }}</option>
                                @endforeach
                            </select>
                            @error('target', 'report')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="report_type">{{ __('reports.fields.type') }}</label>
                            <select class="form-select @error('type', 'report') is-invalid @enderror" name="type" id="report_type" required>
                                @foreach ($reportTypes as $type)
                                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('type', 'report')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label" for="report_description">{{ __('reports.fields.description') }}</label>
                            <textarea class="form-control @error('description', 'report') is-invalid @enderror" name="description" id="report_description" rows="4" minlength="10" maxlength="2000" required>{{ old('description') }}</textarea>
                            @error('description', 'report')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('ui.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('reports.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script type="module">
        const points = @json($points);
        const stages = @json($stageValues);
        const stageLabels = @json(__('impacts.stages'));

        if (points.length > 0) {
            const map = L.map('journeyMap', { scrollWheelZoom: false });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);

            const latLngs = points.map((point) => [point.lat, point.lng]);

            points.forEach((point, index) => {
                L.marker([point.lat, point.lng])
                    .addTo(map)
                    .bindPopup(`<strong>${index + 1}. ${point.type}</strong><br>${point.label.replace(/[<>&]/g, '')}`);
            });

            if (latLngs.length > 1) {
                L.polyline(latLngs, { color: '#1b7f31', weight: 4, opacity: 0.8, dashArray: '8 8' }).addTo(map);
                map.fitBounds(latLngs, { padding: [40, 40] });
            } else {
                map.setView(latLngs[0], 10);
            }
        }

        const canvas = document.getElementById('stagesChart');

        if (canvas) {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(stages).map((key) => stageLabels[key]),
                    datasets: [{
                        data: Object.values(stages),
                        backgroundColor: ['#2aa63e', '#f1c232', '#0f8f7a', '#6c757d'],
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: (context) => `${context.label} : ${context.parsed} kg CO₂e` } },
                    },
                },
            });
        }

        // Reopen the report dialog when its validation failed.
        @if ($errors->report->isNotEmpty())
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        @endif
    </script>
@endpush
