{{--
    Local AI risk of a certificate. The admin sees the full report (score, reasons, LLM explanation);
    the owner only sees the points to fix, when there are some.
--}}
@if ($analysis && auth()->user()->isAdmin())
    <div class="card shadow mb-4 border-left-{{ $analysis->color() }}">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.certification.title') }}</h6>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center flex-wrap mb-2">
                <span class="badge badge-{{ $analysis->color() }} p-2 mr-2" style="font-size: 1rem;">{{ $analysis->levelText() }}</span>
                <span class="small text-gray-600">{{ __('ai.certification.score', ['value' => number_format($analysis->risk * 100, 0)]) }}</span>
            </div>
            <div class="progress mb-3" style="height: .5rem;" role="img" aria-label="{{ __('ai.certification.score', ['value' => number_format($analysis->risk * 100, 0)]) }}">
                <div class="progress-bar bg-{{ $analysis->color() }}" style="width: {{ max(3, $analysis->risk * 100) }}%"></div>
            </div>

            <div class="small font-weight-bold text-gray-700">{{ __('ai.certification.reasons') }}</div>
            <ul class="small mb-3">
                @foreach ($analysis->reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>

            @if ($analysis->explanation)
                <div class="small font-weight-bold text-gray-700">{{ __('ai.certification.explanation') }}</div>
                <p class="mb-2">{{ $analysis->explanation }}</p>
            @elseif ($canExplain)
                <form method="POST" action="{{ route('admin.certifications.explain', $certification) }}" class="mb-3">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-comment-dots fa-sm mr-1"></i> {{ __('ai.certification.explain') }}
                    </button>
                </form>
            @endif

            <p class="small text-muted mb-0">{{ __('ai.certification.admin_help') }}<br>
                {{ __('ai.certification.analyzed_at', ['date' => $analysis->updated_at->format('d/m/Y H:i')]) }}
                @if ($analysis->model) &middot; {{ $analysis->model }} @endif</p>
        </div>
    </div>
@elseif ($analysis?->isSuspicious())
    <div class="card shadow mb-4 border-left-warning">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-warning"><i class="fas fa-exclamation-triangle fa-sm mr-1"></i> {{ __('ai.certification.owner_title') }}</h6>
        </div>
        <div class="card-body">
            <ul class="small mb-2">
                @foreach ($analysis->reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
            <p class="small text-muted mb-0">{{ __('ai.certification.owner_help') }}</p>
        </div>
    </div>
@endif
