@php
    $isActive = request()->routeIs($route) || request()->routeIs($route . '.*');
@endphp

<li class="nav-item">
    <a href="{{ route($route) }}"
       class="nav-link d-flex align-items-center text-white {{ $isActive ? 'active bg-secondary' : '' }}">
        <i class="{{ $icon }} me-2"></i>
        <span>{{ $label }}</span>
    </a>
</li>