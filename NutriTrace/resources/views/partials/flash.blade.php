{{-- Session flash messages. Works in Bootstrap 4 (back office) and Bootstrap 5 (public) markup. --}}
@foreach (['success' => 'success', 'status' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $key => $style)
    @if (session($key))
        <div class="alert alert-{{ $style }} alert-dismissible fade show" role="alert">
            {{ session($key) }}
            @if (($bootstrap ?? 4) === 5)
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ui.close') }}"></button>
            @else
                <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('ui.close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            @endif
        </div>
    @endif
@endforeach
