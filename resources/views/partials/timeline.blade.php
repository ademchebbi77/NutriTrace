{{-- Back office journey timeline (Bootstrap 4 list group). Expects $events and $currentLot. --}}
<ol class="list-group list-group-flush">
    @foreach ($events as $event)
        <li class="list-group-item px-0">
            <div class="d-flex">
                <div class="mr-3">
                    <span class="btn btn-{{ $event->event_type->color() }} btn-circle btn-sm" aria-hidden="true">
                        <i class="fas {{ $event->event_type->icon() }}"></i>
                    </span>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between flex-wrap">
                        <span class="font-weight-bold text-gray-800">
                            {{ $event->event_type->label() }}
                            @unless ($event->lot->is($currentLot))
                                <span class="badge badge-light border">{{ $event->lot->lot_number }}</span>
                            @endunless
                        </span>
                        <span class="small text-gray-600">{{ $event->occurred_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div>{{ $event->description }}</div>
                    <div class="small text-gray-600">
                        @if ($event->location)
                            <i class="fas fa-map-marker-alt fa-sm"></i> {{ $event->location }}
                        @endif
                        @if ($event->actor)
                            &middot; {{ $event->actor->displayName() }}
                        @endif
                    </div>
                    <div class="small text-gray-500 text-monospace text-truncate" style="max-width: 22rem;" title="{{ $event->hash }}">
                        <i class="fas fa-link fa-sm"></i> {{ substr($event->hash, 0, 16) }}…
                    </div>
                </div>
            </div>
        </li>
    @endforeach
</ol>
