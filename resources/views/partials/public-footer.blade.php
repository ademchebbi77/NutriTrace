<!-- Footer-->
<footer class="footer bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 h-100 text-center text-lg-start my-auto">
                <ul class="list-inline mb-2">
                    <li class="list-inline-item"><a href="{{ route('home') }}">{{ __('ui.nav.home') }}</a></li>
                    @foreach (['catalog.index' => 'ui.footer.catalog', 'how-it-works' => 'ui.footer.how_it_works'] as $routeName => $label)
                        @if (Route::has($routeName))
                            <li class="list-inline-item">⋅</li>
                            <li class="list-inline-item"><a href="{{ route($routeName) }}">{{ __($label) }}</a></li>
                        @endif
                    @endforeach
                </ul>
                <p class="text-muted small mb-4 mb-lg-0">&copy; {{ config('app.name') }} {{ now()->year }}. {{ __('ui.copyright') }}</p>
            </div>
            <div class="col-lg-6 h-100 text-center text-lg-end my-auto">
                <p class="text-muted small mb-0"><i class="bi-flower1 text-success me-1"></i>{{ __('ui.tagline') }}</p>
            </div>
        </div>
    </div>
</footer>
