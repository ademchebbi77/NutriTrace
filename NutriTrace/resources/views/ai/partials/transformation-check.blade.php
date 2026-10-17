{{-- Local AI check of a saved transformation: the one the transformer saw, or the admin review. --}}
@if ($aiCheck)
    <div class="card shadow mb-4 border-left-{{ $aiCheck->color() }}">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.transformation.title') }}</h6>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center flex-wrap mb-2">
                <span class="badge badge-{{ $aiCheck->color() }} p-2 mr-2">{{ $aiCheck->labelText() }}</span>
                @if ($aiCheck->is_anomaly)
                    <span class="small text-gray-600">{{ __('ai.transformation.risk_value', ['value' => number_format($aiCheck->risk * 100, 0)]) }}</span>
                @endif
            </div>
            @include('ai.partials.transformation-expected', ['expected' => $aiCheck->expected])

            <ul class="small mb-3">
                @foreach ($aiCheck->reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>

            @if ($aiCheck->explanation)
                <p class="mb-2">{{ $aiCheck->explanation }}</p>
            @elseif ($canExplain)
                <form method="POST" action="{{ route('admin.transformations.explain', $transformation) }}" class="mb-3">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-comment-dots fa-sm mr-1"></i> {{ __('ai.transformation.explain') }}
                    </button>
                </form>
            @endif

            <p class="small text-muted mb-0">
                {{ $aiCheck->audience === \App\Models\TransformationCheck::TRANSFORMER ? __('ai.transformation.checked_before_saving') : __('ai.transformation.admin_help') }}
                @if ($aiCheck->model) &middot; {{ $aiCheck->model }} @endif
            </p>
        </div>
    </div>
@endif
