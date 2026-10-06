{{-- "Pourquoi ce score ?" breakdown. Works in Bootstrap 4 and 5. Expects $trust (TrustScore). --}}
@foreach ($trust->components as $component)
    <div class="mb-3">
        <div class="d-flex justify-content-between small">
            <span class="font-weight-bold fw-bold">{{ $component['label'] }}</span>
            <span>{{ __('trust.points', ['points' => rtrim(rtrim(number_format($component['points'], 1, ',', ' '), '0'), ','), 'max' => (int) $component['max']]) }}</span>
        </div>
        <div class="progress" style="height: 0.5rem;" role="progressbar" aria-label="{{ $component['label'] }}"
            aria-valuenow="{{ round($component['points']) }}" aria-valuemin="0" aria-valuemax="{{ (int) $component['max'] }}">
            <div class="progress-bar bg-{{ $component['points'] >= $component['max'] * 0.75 ? 'success' : ($component['points'] >= $component['max'] * 0.4 ? 'warning' : 'danger') }}"
                style="width: {{ $component['max'] > 0 ? round($component['points'] / $component['max'] * 100) : 0 }}%"></div>
        </div>
        <div class="small text-muted">{{ $component['detail'] }}</div>
    </div>
@endforeach

<div class="small font-weight-bold fw-bold mt-3">{{ __('trust.penalties_title') }}</div>
@forelse ($trust->penalties as $penalty)
    <div class="d-flex justify-content-between small text-danger">
        <span>{{ $penalty['label'] }} (× {{ $penalty['count'] }})</span>
        <span>− {{ rtrim(rtrim(number_format($penalty['points'], 1, ',', ' '), '0'), ',') }}</span>
    </div>
@empty
    <div class="small text-muted">{{ __('trust.no_penalty') }}</div>
@endforelse
