{{-- Local AI advice before the distributor accepts a lot (pending distributions only). --}}
@if ($receptionCheck || $canCheck)
    <div class="card shadow mb-4 border-left-{{ $receptionCheck?->color() ?? 'primary' }}">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.reception.title') }}</h6>
        </div>
        <div class="card-body">
            @if ($receptionCheck)
                <div class="text-xs font-weight-bold text-uppercase text-gray-600 mb-1">{{ __('ai.reception.recommendation') }}</div>
                <div class="d-flex align-items-center flex-wrap mb-2">
                    <span class="badge badge-{{ $receptionCheck->color() }} p-2 mr-2" style="font-size: 1rem;">{{ $receptionCheck->decisionText() }}</span>
                    <span class="small text-gray-600">{{ __('ai.reception.confidence', ['value' => number_format($receptionCheck->confidence * 100, 0)]) }}</span>
                </div>

                <div class="small font-weight-bold text-gray-700">{{ __('ai.reception.reasons') }}</div>
                <ul class="small mb-3">
                    @foreach ($receptionCheck->reasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>

                @if ($receptionCheck->explanation)
                    <div class="small font-weight-bold text-gray-700">{{ __('ai.reception.explanation') }}</div>
                    <p class="mb-2">{{ $receptionCheck->explanation }}</p>
                @endif

                <p class="small text-muted mb-0">{{ __('ai.reception.checked_at', ['date' => $receptionCheck->created_at->format('d/m/Y H:i')]) }}
                    @if ($receptionCheck->model) &middot; {{ $receptionCheck->model }} @endif
                    <br>{{ __('ai.reception.decide') }}</p>
            @else
                <p class="small text-muted">{{ __('ai.reception.help') }}</p>
                <form method="POST" action="{{ route('distributeur.distributions.check', $distribution) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-search fa-sm mr-1"></i> {{ __('ai.reception.run') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
