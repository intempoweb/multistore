<div class="storefront-topbar bg-dark text-white py-2 small">
    <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="d-inline-flex align-items-center gap-1">
                <i class="fa-solid {{ $isB2b ? 'fa-building' : 'fa-store' }}"></i>
                <span>{{ $storeName }}</span>
            </span>

            <span class="badge {{ $isB2b ? 'text-bg-primary' : 'text-bg-success' }}">
                {{ $isB2b ? 'B2B' : 'B2C' }}
            </span>

            @if($storeEmail)
                <a href="mailto:{{ $storeEmail }}" class="text-white-50 text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-envelope"></i>
                    <span>{{ $storeEmail }}</span>
                </a>
            @endif

            @if($storePhone)
                <a href="tel:{{ preg_replace('/\s+/', '', (string) $storePhone) }}" class="text-white-50 text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-phone"></i>
                    <span>{{ $storePhone }}</span>
                </a>
            @endif

            @if($storeVat)
                <span class="text-white-50 d-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-receipt"></i>
                    <span>P. IVA {{ $storeVat }}</span>
                </span>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            @if(Route::has('storefront.catalog.index'))
                <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="text-white-50 text-decoration-none">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>
                    Catalogo
                </a>
            @endif

            @auth('customer')
                @if(Route::has('storefront.wishlist.index'))
                    <a href="{{ route('storefront.wishlist.index', $contextParams) }}" class="text-white-50 text-decoration-none">
                        <i class="fa-regular fa-heart me-1"></i>
                        Preferiti
                    </a>
                @endif

                @if($isB2b)
                    <a href="{{ $documentsUrl }}" class="text-white-50 text-decoration-none">
                        <i class="fa-solid fa-file-lines me-1"></i>
                        Area documentale
                    </a>
                @endif

                @if(Route::has('storefront.account.index'))
                    <a href="{{ route('storefront.account.index', $contextParams) }}" class="text-white-50 text-decoration-none">
                        <i class="fa-solid fa-user me-1"></i>
                        Area cliente
                    </a>
                @endif

                @if(Route::has('storefront.logout'))
                    <a href="{{ route('storefront.logout') }}" class="text-white-50 text-decoration-none">
                        <i class="fa-solid fa-right-from-bracket me-1"></i>
                        Logout
                    </a>
                @endif
            @else
                @if(Route::has('storefront.login'))
                    <a href="{{ route('storefront.login', $contextParams) }}" class="text-white-50 text-decoration-none">
                        <i class="fa-solid fa-right-to-bracket me-1"></i>
                        Accedi
                    </a>
                @endif
            @endauth
        </div>
    </div>
</div>
