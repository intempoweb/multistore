<nav class="navbar navbar-expand bg-white border-bottom">
    <div class="container-fluid">

        <button class="btn btn-outline-secondary d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#adminSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <span class="ms-2 d-none d-lg-inline text-secondary">
            @yield('breadcrumb', 'Dashboard')
        </span>

        <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">

            <div class="me-2">
                @php
                    $activeAdminStore = app()->bound('adminStore') ? app('adminStore') : null;
                @endphp

                @if($activeAdminStore)
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">
                            <i class="fa-solid fa-store me-1"></i>
                            {{ $activeAdminStore->name }}
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end">
                            @forelse(($adminStores ?? collect()) as $storeItem)
                                <li>
                                    <form method="POST" action="{{ route('admin.store.set', $storeItem->id) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="dropdown-item d-flex justify-content-between align-items-center {{ (int) $activeAdminStore->id === (int) $storeItem->id ? 'active' : '' }}"
                                        >
                                            <span>
                                                {{ $storeItem->name }}
                                                <span class="text-muted small ms-1">
                                                    ({{ $storeItem->ditta_cg18 }}/{{ $storeItem->erp_site_code }})
                                                </span>
                                            </span>

                                            @if((int) $activeAdminStore->id === (int) $storeItem->id)
                                                <i class="fa-solid fa-check"></i>
                                            @endif
                                        </button>
                                    </form>
                                </li>
                            @empty
                                <li>
                                    <span class="dropdown-item-text text-muted">Nessuno store disponibile</span>
                                </li>
                            @endforelse
                        </ul>
                    </div>
                @else
                    <span class="badge text-bg-secondary">
                        <i class="fa-solid fa-store me-1"></i>
                        Store admin non disponibile
                    </span>
                @endif
            </div>

            <div class="d-flex align-items-center gap-1">
                <form method="POST" action="{{ route('admin.locale.set', 'it') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-dark {{ app()->getLocale() === 'it' ? 'active' : '' }}">
                        IT
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.locale.set', 'en') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-dark {{ app()->getLocale() === 'en' ? 'active' : '' }}">
                        EN
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.locale.set', 'es') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-dark {{ app()->getLocale() === 'es' ? 'active' : '' }}">
                        ES
                    </button>
                </form>
            </div>

            @auth
                <div class="dropdown ms-2">
                    <button class="btn btn-sm btn-dark dropdown-toggle" data-bs-toggle="dropdown" type="button">
                        <i class="fa-solid fa-user me-1"></i>
                        {{ auth()->user()->name }}
                        <span class="opacity-75">
                            ({{ auth()->user()->is_admin ? 'Admin' : 'User' }})
                        </span>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end">
                       {{--  <li>
                            <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                <i class="fa-solid fa-id-card me-2"></i>
                                Profilo
                            </a>
                        </li> --}}

                        <li><hr class="dropdown-divider"></li>

                        <li>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger" type="submit">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i>
                                    Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endauth

        </div>
    </div>
</nav>