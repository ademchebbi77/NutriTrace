@extends('layouts.layout')

@section('title', __('productions.show_title', ['date' => $production->production_date->format('d/m/Y')]))

@section('content')

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            {{ $production->product->name }}
            <small class="text-gray-600">{{ $production->production_date->format('d/m/Y') }}</small>
        </h1>
        <div>
            <a href="{{ area_route('productions.index') }}" class="btn btn-sm btn-light shadow-sm">
                <i class="fas fa-arrow-left fa-sm"></i> {{ __('ui.back') }}</a>
            @can('update', $production)
                <a href="{{ area_route('productions.edit', $production) }}" class="btn btn-sm btn-primary shadow-sm">
                    <i class="fas fa-pen fa-sm text-white-50"></i> {{ __('ui.edit') }}</a>
            @endcan
        </div>
    </div>

    @if ($production->producer_id === auth()->id() && ! $production->isEditable())
        <div class="alert alert-secondary" role="note">
            <i class="fas fa-lock mr-1"></i> {{ __('productions.locked') }}
        </div>
    @endif

    <div class="row">

        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('productions.sections.product') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ __('productions.fields.product') }}</dt>
                        <dd class="col-sm-7">{{ $production->product->name }} ({{ $production->product->category->name }})</dd>

                        <dt class="col-sm-5">{{ __('productions.fields.quantity') }}</dt>
                        <dd class="col-sm-7">{{ format_quantity($production->quantity, $production->unit) }}</dd>

                        <dt class="col-sm-5">{{ __('productions.fields.production_method') }}</dt>
                        <dd class="col-sm-7">{{ $production->production_method->label() }}</dd>

                        <dt class="col-sm-5">{{ __('productions.fields.producer') }}</dt>
                        <dd class="col-sm-7">{{ $production->producer->organization?->name ?? $production->producer->name }}</dd>

                        <dt class="col-sm-5">{{ __('productions.fields.location') }}</dt>
                        <dd class="col-sm-7 mb-0">
                            {{ collect([$production->location_address, $production->location_city])->filter()->join(', ') }}
                            @if ($production->hasCoordinates())
                                <br><span class="small text-gray-600"><i class="fas fa-map-marker-alt fa-sm"></i>
                                    {{ $production->latitude }}, {{ $production->longitude }}</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('productions.sections.resources') }}</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        @foreach (App\Models\Production::RESOURCE_KEYS as $key)
                            <dt class="col-sm-5">{{ __('productions.fields.'.$key) }}</dt>
                            <dd class="col-sm-7">
                                @isset($production->resources_used[$key])
                                    {{ number_format((float) $production->resources_used[$key], 2, ',', ' ') }}
                                @else
                                    <span class="text-gray-500">{{ __('productions.fields.not_declared') }}</span>
                                @endisset
                            </dd>
                        @endforeach
                        @if (! empty($production->resources_used['notes']))
                            <dt class="col-sm-5">{{ __('productions.fields.notes') }}</dt>
                            <dd class="col-sm-7 mb-0">{{ $production->resources_used['notes'] }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @if ($production->lot)
                <div class="card border-left-primary shadow mb-4">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">{{ __('productions.sections.lot') }}</div>
                        <div class="h5 mb-2 font-weight-bold text-gray-800">{{ $production->lot->lot_number }}</div>
                        <span class="badge badge-{{ $production->lot->status->color() }}">{{ $production->lot->status->label() }}</span>
                        <dl class="row mt-3 mb-0 small">
                            <dt class="col-6">{{ __('lots.fields.quantity') }}</dt>
                            <dd class="col-6">{{ $production->lot->formattedQuantity() }}</dd>
                            <dt class="col-6">{{ __('lots.fields.holder') }}</dt>
                            <dd class="col-6">{{ $production->lot->currentHolder->organization?->name ?? $production->lot->currentHolder->name }}</dd>
                            <dt class="col-6">{{ __('lots.fields.expiration_date') }}</dt>
                            <dd class="col-6 mb-0">{{ $production->lot->expiration_date?->format('d/m/Y') ?? __('lots.fields.none') }}</dd>
                        </dl>
                        <a href="{{ area_route('lots.show', $production->lot) }}" class="btn btn-primary btn-sm mt-3">
                            <i class="fas fa-boxes fa-sm mr-1"></i> {{ __('ui.view') }}</a>
                    </div>
                </div>
            @endif

            {{-- AI Production Forecasting Panel --}}
            <div class="card shadow mb-4" id="production-ai-card">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line fa-sm mr-1"></i> {{ __('ai.production.title') }}
                    </h6>
                    <span id="ai-forecast-badge" class="badge badge-secondary">{{ __('ai.production.loading') }}</span>
                </div>
                <div class="card-body" id="ai-forecast-body">
                    <div class="text-center text-gray-500 py-3">
                        <i class="fas fa-spinner fa-spin fa-lg mb-2"></i>
                        <p class="small mb-0">{{ __('ai.production.loading') }}</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

@endsection

@push('scripts')
<script>
(function () {
    const url   = "{{ route('producteur.productions.forecast', $production) }}";
    const body  = document.getElementById('ai-forecast-body');
    const badge = document.getElementById('ai-forecast-badge');

    const STATUS_CLASSES = {
        ok:                  'badge-success',
        statistical_only:    'badge-warning',
        insufficient_data:   'badge-warning',
        no_data:             'badge-secondary',
        service_unavailable: 'badge-danger',
    };

    const STATUS_LABELS  = @json(__('ai.production.status'));
    const TREND_LABELS   = {
        increasing: "{{ __('ai.production.trend_increasing') }} ↑",
        decreasing: "{{ __('ai.production.trend_decreasing') }} ↓",
        stable:     "{{ __('ai.production.trend_stable') }} →",
    };

    // French symbol for the "unit" unit (pièce(s)); others (kg, t, L) stay as-is.
    const UNIT_SYMBOLS = @json(['unit' => __('enums.unit_symbol.unit')]);

    const dateFmt = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });

    function unitLabel(unit) {
        return UNIT_SYMBOLS[unit] || unit || '';
    }

    function fmtAxis(value) {
        if (value === null || value === undefined || !isFinite(value)) return '—';
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value);
    }

    function fmtNum(value, unit) {
        if (value === null || value === undefined) return '—';
        return fmtAxis(value) + (unit ? ' ' + unitLabel(unit) : '');
    }

    function fmtDate(iso) {
        return dateFmt.format(new Date(iso + 'T00:00:00'));
    }

    function periodLabel(period) {
        const parts = String(period).split('-');
        return parts.length === 2 ? parts[1] + '/' + parts[0] : period;
    }

    // Draws the formatted quantity above each bar so small bars stay readable.
    function valueLabelsPlugin(unit) {
        return {
            id: 'productionValueLabels',
            afterDatasetsDraw: function (chart) {
                const ctx  = chart.ctx;
                const meta = chart.getDatasetMeta(0);
                ctx.save();
                ctx.fillStyle   = '#5a5c69';
                ctx.font        = '600 10px Nunito, sans-serif';
                ctx.textAlign   = 'center';
                ctx.textBaseline = 'bottom';
                chart.data.datasets[0].data.forEach(function (value, i) {
                    ctx.fillText(fmtAxis(value) + ' ' + unitLabel(unit), meta.data[i].x, meta.data[i].y - 2);
                });
                ctx.restore();
            },
        };
    }

    function drawChart(history, unit) {
        const canvas = document.getElementById('production-history-chart');
        if (!canvas || !history.length) return;

        // Defensive: the server sends chronological data, sort again anyway.
        const rows = history.slice().sort(function (a, b) { return a.date < b.date ? -1 : 1; });
        const quantities = rows.map(function (r) { return Number(r.quantity); });
        const max = Math.max.apply(null, quantities);
        const min = Math.min.apply(null, quantities);
        // Honest readability: with hugely different quantities a linear scale
        // makes small bars invisible, so switch to log and say it explicitly.
        const useLog = min > 0 && max / min >= 20;

        new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: rows.map(function (r) { return fmtDate(r.date); }),
                datasets: [{
                    label: unitLabel(unit),
                    data: quantities,
                    backgroundColor: 'rgba(78,115,223,0.55)',
                    borderColor: 'rgba(78,115,223,1)',
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 18 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                return items.length ? fmtDate(rows[items[0].dataIndex].date) : '';
                            },
                            label: function (item) {
                                return "{{ __('ai.production.tooltip_quantity') }} : "
                                     + fmtAxis(item.parsed.y) + ' ' + unitLabel(unit);
                            },
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: useLog
                        ? { type: 'logarithmic', ticks: { callback: function (v) { return fmtAxis(v); } } }
                        : { beginAtZero: true, ticks: { callback: function (v) { return fmtAxis(v); } } },
                },
            },
            plugins: [valueLabelsPlugin(unit)],
        });

        if (useLog) {
            const caption = document.createElement('p');
            caption.className = 'small text-gray-500 mb-0 mt-1';
            caption.innerHTML = '<i class="fas fa-ruler-vertical fa-xs mr-1"></i>'
                              + "{{ __('ai.production.log_scale') }}";
            canvas.parentElement.appendChild(caption);
        }
    }

    function renderForecast(d) {
        const unit   = d.unit || '';
        const status = d.status || 'service_unavailable';

        if (status === 'ok') {
            const isMl = d.model_info && d.model_info.name === 'gradient_boosting';
            badge.className   = 'badge ' + (isMl ? 'badge-success' : 'badge-info');
            badge.textContent = isMl ? (STATUS_LABELS.ok || 'ok')
                                     : (STATUS_LABELS.baseline_only || 'Estimation statistique');
        } else {
            badge.className   = 'badge ' + (STATUS_CLASSES[status] || 'badge-secondary');
            badge.textContent = STATUS_LABELS[status] || status;
        }

        if (status === 'service_unavailable' || status === 'no_data') {
            body.innerHTML = '<p class="small text-gray-600 mb-0">' + (d.explanation || '') + '</p>';
            return;
        }

        let html = '';

        if (d.history && d.history.length) {
            html += '<p class="text-xs font-weight-bold text-uppercase text-gray-600 mb-1">'
                  + "{{ __('ai.production.history_chart') }}" + '</p>'
                  + '<div style="height: 240px; position: relative;"><canvas id="production-history-chart"></canvas></div>';
        }

        if (d.statistics) {
            html += '<hr class="my-3">'
                  + '<p class="text-xs font-weight-bold text-uppercase text-gray-600 mb-1">'
                  + "{{ __('ai.production.statistics') }}" + '</p>'
                  + '<dl class="row small mb-0">'
                  + '<dt class="col-7">{{ __('ai.production.mean') }}</dt><dd class="col-5">' + fmtNum(d.statistics.mean, unit) + '</dd>'
                  + '<dt class="col-7">{{ __('ai.production.min') }}</dt><dd class="col-5">' + fmtNum(d.statistics.min, unit) + '</dd>'
                  + '<dt class="col-7">{{ __('ai.production.max') }}</dt><dd class="col-5">' + fmtNum(d.statistics.max, unit) + '</dd>'
                  + '<dt class="col-7">{{ __('ai.production.last') }}</dt><dd class="col-5 mb-0">' + fmtNum(d.statistics.last, unit) + '</dd>'
                  + '</dl>';

            if (d.baseline_estimate !== null && d.baseline_estimate !== undefined) {
                html += '<p class="small mt-2 mb-1"><strong>{{ __('ai.production.baseline') }} :</strong> '
                      + fmtNum(d.baseline_estimate, unit) + '</p>';
            }
        }

        if (d.trend_per_year !== null && d.trend_per_year !== undefined) {
            const dir = d.trend_direction ? (TREND_LABELS[d.trend_direction] || '') : '';
            html += '<p class="small mt-2 mb-1"><strong>{{ __('ai.production.trend') }} :</strong> '
                  + fmtNum(d.trend_per_year, unit + '/an') + ' ' + dir + '</p>';
        }

        if (d.prediction !== null && d.prediction !== undefined) {
            // A trained ML model beat the baseline on chronological validation.
            html += '<hr class="my-3">'
                  + '<p class="text-xs font-weight-bold text-uppercase text-gray-600 mb-1">'
                  + "{{ __('ai.production.prediction') }}" + '</p>'
                  + '<p class="h5 mb-1 text-success font-weight-bold">' + fmtNum(d.prediction, unit) + '</p>';
            if (d.prediction_period) {
                html += '<p class="small text-gray-600 mb-1"><strong>{{ __('ai.production.period') }} :</strong> '
                      + periodLabel(d.prediction_period) + '</p>';
            }
            if (d.model_info && d.model_info.mae !== null && d.model_info.mae !== undefined) {
                html += '<p class="small text-gray-600 mb-1"><strong>{{ __('ai.production.mae') }} :</strong> '
                      + fmtNum(d.model_info.mae, unit) + '</p>';
                if (d.model_info.test_size) {
                    html += '<p class="small text-gray-600 mb-1">'
                          + "{{ __('ai.production.mae_explanation') }}".replace(':count', d.model_info.test_size)
                          + '</p>';
                }
            }
            if (d.model_info && d.model_info.reason) {
                html += '<p class="small text-gray-600 mb-1">' + d.model_info.reason + '</p>';
            }
            if (d.prediction_period && d.history && d.history.length
                && d.prediction_period > d.history[d.history.length - 1].date.slice(0, 7)) {
                html += '<p class="small text-warning mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>'
                      + "{{ __('ai.production.horizon_warning') }}" + '</p>';
            }
        } else if (d.baseline_estimate !== null && d.baseline_estimate !== undefined && status === 'ok') {
            // Enough data was available, but the model did not prove useful:
            // show the statistical baseline and explain why.
            html += '<hr class="my-3">'
                  + '<p class="text-xs font-weight-bold text-uppercase text-gray-600 mb-1">'
                  + (STATUS_LABELS.baseline_only || 'Estimation statistique') + '</p>'
                  + '<p class="h5 mb-1 text-info font-weight-bold">' + fmtNum(d.baseline_estimate, unit) + '</p>'
                  + '<p class="small text-gray-600 mb-0">' + "{{ __('ai.production.baseline') }}." + '</p>';
            if (d.model_info && d.model_info.reason) {
                html += '<div class="alert alert-info small py-2 mt-2 mb-0"><i class="fas fa-info-circle mr-1"></i>'
                      + d.model_info.reason + '</div>';
            }
        }

        if (status === 'insufficient_data' || status === 'statistical_only') {
            html += '<div class="alert alert-warning small py-2 mt-3 mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>'
                  + (d.explanation || '') + '</div>';
        }

        html += '<div class="border-top mt-3 pt-2 ai-disclaimer">'
              + '<p class="small text-gray-600 mb-0"><i class="fas fa-info-circle mr-1"></i>'
              + "{{ __('ai.production.disclaimer') }}" + '</p></div>';

        body.innerHTML = html;

        if (d.history && d.history.length) {
            drawChart(d.history, unit);
        }
    }

    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
    .then(function (r) { return r.json(); })
    .then(renderForecast)
    .catch(function () {
        badge.className   = 'badge badge-danger';
        badge.textContent = STATUS_LABELS['service_unavailable'] || 'Hors ligne';
        body.innerHTML    = '<p class="small text-gray-600 mb-0">{{ __('ai.production.unavailable') }}</p>';
    });
})();
</script>
@endpush
