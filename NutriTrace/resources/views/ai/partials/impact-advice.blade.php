{{-- "Conseils IA": how to improve the lot's environmental grade, quantified with NutriTrace's own scoring rules. --}}
@if ($advice || $canAdvise)
    <div class="card shadow mb-4 border-left-info">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-lightbulb fa-sm mr-1"></i> {{ __('ai.advice.title') }}</h6>
        </div>
        <div class="card-body">
            @if ($advice)
                @if ($advice->grade)
                    <div class="mb-2">
                        <span class="badge badge-secondary p-2">{{ __('ai.advice.current', ['grade' => $advice->grade, 'score' => $advice->score]) }}</span>
                        @if ($advice->target_grade)
                            <i class="fas fa-arrow-right fa-sm text-gray-500 mx-1"></i>
                            <span class="badge badge-success p-2">{{ __('ai.advice.target', ['grade' => $advice->target_grade]) }}</span>
                        @endif
                    </div>
                @endif
                <ol class="small mb-3 pl-3">
                    @foreach ($advice->levers as $lever)
                        <li class="mb-1">{{ $lever }}</li>
                    @endforeach
                </ol>
                @if ($advice->explanation)
                    <p class="mb-2">{{ $advice->explanation }}</p>
                @endif
                <p class="small text-muted mb-0">{{ __('ai.advice.help') }} @if ($advice->model) &middot; {{ $advice->model }} @endif</p>
            @else
                <p class="small text-muted">{{ __('ai.advice.intro') }}</p>
                <form method="POST" action="{{ area_route('impacts.advise', $lot) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-info"><i class="fas fa-lightbulb fa-sm mr-1"></i> {{ __('ai.advice.run') }}</button>
                </form>
            @endif
        </div>
    </div>
@endif
