{{--
    Chat box of the AI assistant, for the back office (Bootstrap 4) and the public site (Bootstrap 5):
    only classes common to both. When the AI service is off, the box stays: questions already
    answered for this data come from the cache, the others get a "try later" message.

    @param string $url  POST endpoint returning {answer} or {message}
    @param bool $available
    @param ?string $texts  Translation group of the examples, placeholder and disclaimer (default ai.assistant)
    @param ?string $offline  Message shown when the service is off
--}}
<div class="js-ai-assistant" data-url="{{ $url }}" data-thinking="{{ __('ai.assistant.thinking') }}"
    data-error="{{ __('ai.assistant.error') }}" data-you="{{ __('ai.assistant.you') }}" data-bot="{{ __('ai.assistant.bot') }}">

    <div class="js-ai-log mb-3" aria-live="polite"></div>

    @unless ($available)
        <p class="small text-muted"><i class="fas fa-plug bi-plug" aria-hidden="true"></i> {{ $offline ?? __('ai.assistant.offline') }}</p>
    @endunless

    <div class="mb-3">
        <div class="small text-muted mb-1">{{ __('ai.assistant.suggestions') }}</div>
        @foreach (__(($texts ?? 'ai.assistant').'.examples') as $question)
            <button type="button" class="btn btn-sm btn-outline-secondary mb-1 js-ai-suggestion" data-question="{{ $question }}">{{ $question }}</button>
        @endforeach
    </div>

    <form class="js-ai-form" novalidate>
        <label for="ai-question-{{ md5($url) }}" class="sr-only visually-hidden">{{ __('ai.assistant.question') }}</label>
        <div class="input-group">
            <input type="text" id="ai-question-{{ md5($url) }}" name="question" class="form-control" minlength="3"
                maxlength="{{ config('ai.question_max_length') }}" placeholder="{{ __(($texts ?? 'ai.assistant').'.placeholder') }}" autocomplete="off" required>
            <div class="input-group-append">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane bi-send" aria-hidden="true"></i> {{ __('ai.assistant.ask') }}</button>
            </div>
        </div>
    </form>

    <p class="small text-muted mt-2 mb-0">{{ __(($texts ?? 'ai.assistant').'.disclaimer') }}</p>
</div>

@once
    @push('scripts')
        <script type="module">
            document.querySelectorAll('.js-ai-assistant').forEach((box) => {
                const log = box.querySelector('.js-ai-log');
                const form = box.querySelector('.js-ai-form');
                const buttons = () => box.querySelectorAll('button');
                const csrf = document.querySelector('meta[name="csrf-token"]').content;

                // textContent only: answers are never interpreted as HTML.
                const add = (who, text, mine) => {
                    const item = document.createElement('div');
                    item.className = 'border rounded p-2 mb-2 ' + (mine ? 'bg-light' : 'bg-white');
                    const name = document.createElement('div');
                    name.className = 'small font-weight-bold fw-bold ' + (mine ? 'text-muted' : 'text-primary');
                    name.textContent = who;
                    const body = document.createElement('div');
                    body.style.whiteSpace = 'pre-line';
                    body.textContent = text;
                    item.append(name, body);
                    log.append(item);
                    return body;
                };

                const ask = async (question) => {
                    add(box.dataset.you, question, true);
                    const answer = add(box.dataset.bot, box.dataset.thinking, false);
                    buttons().forEach((button) => button.disabled = true);

                    try {
                        const response = await fetch(box.dataset.url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ question }),
                        });
                        const data = await response.json().catch(() => ({}));
                        answer.textContent = response.ok ? data.answer : (data.errors?.question?.[0] ?? data.message ?? box.dataset.error);
                    } catch {
                        answer.textContent = box.dataset.error;
                    } finally {
                        buttons().forEach((button) => button.disabled = false);
                    }
                };

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const input = form.elements.question;
                    const question = input.value.trim();

                    if (question.length >= 3) {
                        input.value = '';
                        ask(question);
                    }
                });

                box.querySelectorAll('.js-ai-suggestion').forEach((button) => {
                    button.addEventListener('click', () => ask(button.dataset.question));
                });
            });
        </script>
    @endpush
@endonce
