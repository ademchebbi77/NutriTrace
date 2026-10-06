<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ route('dashboard') }}">
        <div class="sidebar-brand-icon">
            <i class="fas fa-seedling"></i>
        </div>
        <div class="sidebar-brand-text mx-3">{{ config('app.name') }}</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item {{ request()->routeIs('dashboard', '*.dashboard') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('dashboard') }}">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>{{ __('menu.dashboard') }}</span></a>
    </li>

    @foreach ($sidebarSections as $section)
        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading -->
        <div class="sidebar-heading">
            {{ $section['heading'] }}
        </div>

        @foreach ($section['items'] as $item)
            <li class="nav-item {{ $item['active'] ? 'active' : '' }}">
                @if ($item['url'])
                    <a class="nav-link" href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>
                        <i class="fas fa-fw {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span></a>
                @else
                    <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true"
                        title="{{ __('menu.coming_soon') }}">
                        <i class="fas fa-fw {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span></a>
                @endif
            </li>
        @endforeach
    @endforeach

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle" aria-label="{{ __('ui.toggle_navigation') }}"></button>
    </div>

</ul>
<!-- End of Sidebar -->
