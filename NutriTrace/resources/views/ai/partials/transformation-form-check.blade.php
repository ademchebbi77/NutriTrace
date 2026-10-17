{{--
    Local AI check on the transformation form. A transformation cannot be edited once saved:
    the transformer can check the values first, and confirms them when the AI finds a problem
    on submit ($pendingCheck). Never blocking.
--}}
@if ($pendingCheck)
    <div class="card shadow mb-4 border-left-{{ $pendingCheck->color() }}" role="alert">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-{{ $pendingCheck->color() }}"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.transformation.pending_title') }}</h6>
        </div>
        <div class="card-body">
            <span class="badge badge-{{ $pendingCheck->color() }} mb-2">{{ $pendingCheck->labelText() }}</span>
            @include('ai.partials.transformation-expected', ['expected' => $pendingCheck->expected])
            <ul class="small mb-3">
                @foreach ($pendingCheck->reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="ai_confirmed" name="ai_confirmed" value="1">
                <label class="custom-control-label" for="ai_confirmed">{{ __('ai.transformation.confirm') }}</label>
            </div>
        </div>
    </div>
@endif

@if ($aiAvailable)
    <div class="card shadow mb-4 js-transformation-check" data-url="{{ route('transformateur.transformations.check') }}"
        data-thinking="{{ __('ai.transformation.thinking') }}" data-error="{{ __('ai.assistant.error') }}" data-incomplete="{{ __('ai.transformation.incomplete') }}"
        data-expected="{{ __('ai.transformation.expected_output', ['min' => ':min', 'max' => ':max', 'unit' => ':unit']) }}"
        data-resources="{{ __('ai.transformation.expected_resources', ['emin' => ':emin', 'emax' => ':emax', 'wmin' => ':wmin', 'wmax' => ':wmax']) }}">
        <div class="card-body">
            <p class="small text-muted">{{ __('ai.transformation.form_help') }}</p>
            <button type="button" class="btn btn-outline-primary btn-block js-check"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.transformation.check') }}</button>
            <div class="mt-3 js-result" aria-live="polite"></div>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            const box = document.querySelector('.js-transformation-check');
            const form = box.closest('form');
            const result = box.querySelector('.js-result');
            const button = box.querySelector('.js-check');
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const number = (value) => Number(value).toLocaleString('fr-FR', { maximumFractionDigits: 1 });
            const element = (tag, className, text) => {
                const node = document.createElement(tag);
                node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            };

            const render = (check) => {
                result.replaceChildren();
                result.append(element('span', `badge badge-${check.color} mb-2`, check.label_text));
                const e = check.expected ?? {};
                if (e.output_min !== undefined) {
                    result.append(element('div', 'small text-gray-700', box.dataset.expected
                        .replace(':min', number(e.output_min)).replace(':max', number(e.output_max)).replace(':unit', e.output_unit ?? '')));
                    result.append(element('div', 'small text-gray-600 mb-2', box.dataset.resources
                        .replace(':emin', number(e.energy_min)).replace(':emax', number(e.energy_max))
                        .replace(':wmin', number(e.water_min)).replace(':wmax', number(e.water_max))));
                }
                const list = element('ul', 'small mb-2');
                check.reasons.forEach((reason) => list.append(element('li', '', reason)));
                result.append(list);
                if (check.explanation) result.append(element('p', 'mb-0', check.explanation));
            };

            button.addEventListener('click', async () => {
                button.disabled = true;
                result.replaceChildren(element('div', 'small text-muted', box.dataset.thinking));
                try {
                    const response = await fetch(box.dataset.url, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                        body: new FormData(form),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (response.ok) {
                        render(data);
                    } else {
                        const first = data.errors ? Object.values(data.errors)[0][0] : null;
                        result.replaceChildren(element('div', 'small text-danger', response.status === 422 ? `${box.dataset.incomplete} ${first ?? ''}` : (data.message ?? box.dataset.error)));
                    }
                } catch {
                    result.replaceChildren(element('div', 'small text-danger', box.dataset.error));
                } finally {
                    button.disabled = false;
                }
            });
        </script>
    @endpush
@endif
