{{--
    Local AI check of the declared figures, inside the declaration form: "Vérifier avec l'IA"
    button, and the confirmation asked when the AI found a problem on save ($pendingCheck).
    Never blocking. Also shows the check of the figures currently saved ($aiCheck).
--}}
@if ($pendingCheck)
    <div class="alert alert-{{ $pendingCheck->color() }} small" role="alert">
        <div class="font-weight-bold mb-1"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.impact.pending_title') }} · {{ $pendingCheck->labelText() }}</div>
        <ul class="mb-2 pl-3">
            @foreach ($pendingCheck->reasons as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="ai_confirmed" name="ai_confirmed" value="1">
            <label class="custom-control-label" for="ai_confirmed">{{ __('ai.impact.confirm') }}</label>
        </div>
    </div>
@elseif ($aiCheck && $aiCheck->is_anomaly)
    <div class="alert alert-light border small" role="note">
        <span class="badge badge-{{ $aiCheck->color() }}">{{ $aiCheck->labelText() }}</span> {{ __('ai.impact.saved_check') }}
        <ul class="mb-0 mt-1 pl-3">
            @foreach ($aiCheck->reasons as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($aiAvailable)
    <div class="mb-3 js-impact-check" data-url="{{ area_route('impacts.check', $lot) }}" data-thinking="{{ __('ai.impact.thinking') }}"
        data-error="{{ __('ai.assistant.error') }}" data-incomplete="{{ __('ai.transformation.incomplete') }}">
        <button type="button" class="btn btn-outline-primary btn-block js-check"><i class="fas fa-robot fa-sm mr-1"></i> {{ __('ai.impact.check') }}</button>
        <div class="mt-2 small js-result" aria-live="polite"></div>
    </div>

    @push('scripts')
        <script type="module">
            const box = document.querySelector('.js-impact-check');
            const form = box.closest('form');
            const result = box.querySelector('.js-result');
            const button = box.querySelector('.js-check');
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const element = (tag, className, text) => {
                const node = document.createElement(tag);
                node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            };

            button.addEventListener('click', async () => {
                button.disabled = true;
                result.replaceChildren(element('div', 'text-muted', box.dataset.thinking));
                const data = new FormData(form);
                data.delete('_method');
                try {
                    const response = await fetch(box.dataset.url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: data });
                    const body = await response.json().catch(() => ({}));
                    result.replaceChildren();
                    if (response.ok) {
                        result.append(element('span', `badge badge-${body.color} mb-1`, body.label_text));
                        const list = element('ul', 'mb-1 pl-3');
                        body.reasons.forEach((reason) => list.append(element('li', '', reason)));
                        result.append(list);
                        if (body.explanation) result.append(element('p', 'mb-0', body.explanation));
                    } else {
                        const first = body.errors ? Object.values(body.errors)[0][0] : null;
                        result.append(element('div', 'text-danger', response.status === 422 && first ? `${box.dataset.incomplete} ${first}` : (body.message ?? box.dataset.error)));
                    }
                } catch {
                    result.replaceChildren(element('div', 'text-danger', box.dataset.error));
                } finally {
                    button.disabled = false;
                }
            });
        </script>
    @endpush
@endif
