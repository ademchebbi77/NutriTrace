{{-- Anomaly check of a transport. Hidden when the AI service is off and nothing is cached. --}}
@if ($analysis || $aiAvailable)
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.analysis.title') }}</h6>
        </div>
        <div class="card-body">
            @if ($analysis)
                <div class="d-flex align-items-center flex-wrap mb-2">
                    <span class="badge badge-{{ $analysis->is_anomaly ? 'danger' : 'success' }} mr-2 p-2">
                        <i class="fas {{ $analysis->is_anomaly ? 'fa-exclamation-triangle' : 'fa-check' }} fa-sm"></i>
                        {{ $analysis->is_anomaly ? __('ai.analysis.anomaly') : __('ai.analysis.normal') }}
                    </span>
                    @if ($analysis->is_anomaly)
                        <span class="font-weight-bold text-gray-800 mr-2">{{ $analysis->labelText() }}</span>
                    @endif
                    <span class="small text-gray-600">{{ __('ai.analysis.confidence', ['value' => number_format($analysis->confidence * 100, 0)]) }}</span>
                </div>

                @if ($analysis->reasons)
                    <div class="small font-weight-bold text-gray-700">{{ __('ai.analysis.reasons') }}</div>
                    <ul class="small mb-3">
                        @foreach ($analysis->reasons as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($analysis->explanation)
                    <div class="small font-weight-bold text-gray-700">{{ __('ai.analysis.explanation') }}</div>
                    <p class="mb-2">{{ $analysis->explanation }}</p>
                @endif

                <p class="small text-muted mb-0">{{ __('ai.analysis.analyzed_at', ['date' => $analysis->created_at->format('d/m/Y H:i')]) }}
                    @if ($analysis->model) &middot; {{ $analysis->model }} @endif</p>
            @else
                @if ($staleAnalysis)
                    <p class="small text-warning mb-2">{{ __('ai.analysis.stale') }}</p>
                @endif
                <p class="small text-muted">{{ __('ai.analysis.help') }}</p>
                <form method="POST" action="{{ area_route('transports.analyze', $transport) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-search fa-sm mr-1"></i> {{ $staleAnalysis ? __('ai.analysis.rerun') : __('ai.analysis.run') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
