@extends('layouts.layout')

@section('title', __('ai.admin.title'))

@section('content')

    <!-- Page Heading -->
    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.admin.title') }}</h1>

    @php($up = (bool) ($health['ready'] ?? false) || ($health['training'] ?? false))

    <div class="row">

        <!-- Service -->
        <div class="col-lg-5">
            <div class="card shadow mb-4 border-left-{{ $up ? 'success' : 'danger' }}">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('ai.admin.service') }}</h6>
                </div>
                <div class="card-body">
                    @if ($up)
                        <dl class="row mb-0 small">
                            <dt class="col-sm-5">{{ __('ai.admin.service') }}</dt>
                            <dd class="col-sm-7"><span class="badge badge-success">{{ __('ai.admin.service_up') }}</span>
                                @if ($health['training'] ?? false) <span class="badge badge-info">{{ __('ai.admin.states.running') }}</span> @endif</dd>
                            <dt class="col-sm-5">{{ __('ai.admin.llm') }}</dt>
                            <dd class="col-sm-7">{{ $health['model'] ?? __('ai.admin.not_loaded') }}</dd>
                            <dt class="col-sm-5">{{ __('ai.admin.adapters') }}</dt>
                            <dd class="col-sm-7">
                                @forelse ($health['adapters'] ?? [] as $adapter)
                                    <span class="badge badge-primary">{{ __('ai.admin.modules.'.$adapter) }}</span>
                                @empty
                                    {{ __('ai.admin.none') }}
                                @endforelse
                            </dd>
                            <dt class="col-sm-5">{{ __('ai.admin.classifiers') }}</dt>
                            <dd class="col-sm-7 mb-0">
                                @foreach (['anomaly' => 'anomaly', 'cert' => 'certification', 'reception' => 'reception', 'transformation' => 'transformation', 'shelf' => 'shelf_life'] as $name => $key)
                                    <div><i class="fas fa-{{ ($health[$key] ?? false) ? 'check text-success' : 'times text-danger' }} fa-sm"></i>
                                        {{ __('ai.admin.classifier_names.'.$name) }}</div>
                                @endforeach
                            </dd>
                        </dl>
                        @foreach ($health['errors'] ?? [] as $error)
                            <div class="small text-warning mt-2"><i class="fas fa-exclamation-triangle fa-sm"></i> {{ $error }}</div>
                        @endforeach
                    @else
                        <p class="mb-0 text-danger"><i class="fas fa-plug fa-sm mr-1"></i> {{ __('ai.admin.service_down') }}</p>
                    @endif
                </div>
            </div>

            <!-- Training -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('ai.admin.train_title') }}</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">{{ __('ai.admin.train_help') }}</p>
                    <form method="POST" action="{{ route('admin.ai.train') }}" onsubmit="return confirm(@js(__('ai.admin.confirm')))">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-8">
                                <label for="module">{{ __('ai.admin.module') }}</label>
                                <select name="module" id="module" class="custom-select">
                                    @foreach ($modules as $module)
                                        <option value="{{ $module }}">{{ __('ai.admin.modules.'.$module) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-4">
                                <label for="epochs">{{ __('ai.admin.epochs') }}</label>
                                <input type="number" name="epochs" id="epochs" value="1" min="0.5" max="3" step="0.5" class="form-control">
                            </div>
                        </div>
                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" class="custom-control-input" id="resume" name="resume" value="1">
                            <label class="custom-control-label small" for="resume">{{ __('ai.admin.resume') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block js-train-start" @disabled(! $up || ($health['training'] ?? false))>
                            <i class="fas fa-play fa-sm mr-1"></i> {{ __('ai.admin.start') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.ai.cancel') }}" class="mt-2 js-train-cancel {{ ($health['training'] ?? false) ? '' : 'd-none' }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-block"><i class="fas fa-stop fa-sm mr-1"></i> {{ __('ai.admin.cancel') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <!-- Progress -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">{{ __('ai.admin.progress') }}</h6>
                    <span class="badge badge-secondary js-state">…</span>
                </div>
                <div class="card-body">
                    <div class="small text-gray-700 mb-1"><span class="js-module"></span> <span class="js-stage"></span></div>
                    <div class="progress mb-2" style="height: 1rem;">
                        <div class="progress-bar progress-bar-striped js-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="small text-gray-600 js-details"></div>
                    <div class="small text-danger js-error"></div>
                    <details class="mt-2">
                        <summary class="small">{{ __('ai.admin.log') }}</summary>
                        <pre class="small bg-light p-2 mb-0 js-log" style="max-height: 14rem; overflow: auto; white-space: pre-wrap;"></pre>
                    </details>
                </div>
            </div>

            <!-- Models -->
            @if ($models)
                @foreach ($models['adapters'] as $adapter)
                    <div class="card shadow mb-3">
                        <div class="card-body py-3">
                            <div class="font-weight-bold text-gray-800">{{ __('ai.admin.modules.'.$adapter['module']) }}
                                <span class="small text-gray-600">· LoRA</span></div>
                            @if ($adapter['trained'] && isset($adapter['training']))
                                <div class="small text-gray-700">
                                    {{ __('ai.admin.trained', ['date' => \Illuminate\Support\Carbon::parse($adapter['training']['trained_at'] ?? now())->format('d/m/Y H:i')]) }}
                                    · {{ $adapter['training']['base_model'] ?? '' }}
                                    · {{ __('ai.admin.examples', ['count' => $adapter['training']['train_examples'] ?? '?']) }}
                                    · {{ __('ai.admin.losses', ['train' => $adapter['training']['train_loss'] ?? '?', 'eval' => $adapter['training']['eval_loss'] ?? '?']) }}
                                </div>
                                @isset($adapter['evaluation'])
                                    <div class="small text-success">{{ __('ai.admin.eval', ['value' => number_format(($adapter['evaluation']['accuracy'] ?? 0) * 100, 1, ',', ' '), 'count' => $adapter['evaluation']['questions'] ?? 0]) }}</div>
                                @endisset
                            @else
                                <div class="small text-warning">{{ __('ai.admin.not_trained') }}</div>
                            @endif
                            @if ($adapter['checkpoint'] ?? null)
                                <div class="small text-gray-600">{{ __('ai.admin.checkpoint', ['name' => $adapter['checkpoint']]) }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @foreach ($models['classifiers'] as $name => $classifier)
                    <div class="card shadow mb-3">
                        <div class="card-body py-3">
                            <div class="font-weight-bold text-gray-800">{{ __('ai.admin.classifier_names.'.$name) }}
                                <span class="small text-gray-600">· random forest</span></div>
                            @if ($classifier['metrics'])
                                <div class="small text-gray-700">
                                    {{ __('ai.admin.accuracy') }} : {{ number_format($classifier['metrics']['accuracy'] * 100, 1, ',', ' ') }} %
                                    · F1 : {{ number_format($classifier['metrics']['macro_f1'] * 100, 1, ',', ' ') }} %
                                    @if (($classifier['metrics']['real_accuracy'] ?? null) !== null)
                                        · {{ __('ai.admin.real_accuracy') }} : {{ number_format($classifier['metrics']['real_accuracy'] * 100, 1, ',', ' ') }} %
                                    @endif
                                </div>
                            @else
                                <div class="small text-warning">{{ __('ai.admin.not_trained') }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

@endsection

@push('scripts')
    <script type="module">
        const texts = @json($scriptTexts);
        const colors = { running: 'info', done: 'success', failed: 'danger', cancelled: 'secondary', idle: 'secondary', unreachable: 'danger' };
        const $ = (selector) => document.querySelector(selector);
        let wasRunning = null;

        const render = (s) => {
            const state = s.running ? 'running' : (s.state ?? 'idle');
            $('.js-state').className = `badge badge-${colors[state] ?? 'secondary'} js-state`;
            $('.js-state').textContent = texts.states[state] ?? state;
            $('.js-module').textContent = s.module ? texts.modules[s.module] + ' —' : '';
            $('.js-stage').textContent = s.stage ? (texts.stages[s.stage] ?? s.stage) : '';

            const percent = s.stage === 'done' ? 100 : (s.total ? Math.round(100 * s.step / s.total) : 0);
            $('.js-bar').style.width = `${percent}%`;
            $('.js-bar').textContent = percent ? `${percent} %` : '';
            $('.js-bar').classList.toggle('progress-bar-animated', state === 'running');

            const details = [];
            if (s.total) details.push(texts.step.replace(':step', s.step).replace(':total', s.total));
            if (state === 'running' && s.eta_seconds) details.push(texts.eta.replace(':minutes', Math.ceil(s.eta_seconds / 60)));
            if (s.loss != null) details.push(texts.loss.replace(':value', s.loss));
            if (s.accuracy != null) details.push(`${Math.round(s.accuracy * 1000) / 10} %`);
            $('.js-details').textContent = [s.message, ...details].filter(Boolean).join(' · ');
            $('.js-error').textContent = s.error ?? '';
            $('.js-log').textContent = (s.log ?? []).join('\n');

            $('.js-train-cancel').classList.toggle('d-none', state !== 'running');
            $('.js-train-start').disabled = state === 'running' || state === 'unreachable';
            // Reload the page when a run ends, to show the new scores.
            if (wasRunning && state !== 'running') window.location.reload();
            wasRunning = state === 'running';
        };

        const poll = async () => {
            try {
                const response = await fetch(@js(route('admin.ai.status')), { headers: { Accept: 'application/json' } });
                render(await response.json());
            } catch {
                render({ state: 'unreachable' });
            }
        };

        poll();
        setInterval(poll, 3000);
    </script>
@endpush
