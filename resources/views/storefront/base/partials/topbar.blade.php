@php
    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $storeName = $store->name ?? config('app.name', 'Store');
    $isB2b = (bool) ($store?->is_b2b ?? false);

    $storeEmail = $store->email
        ?? $store->support_email
        ?? $store->customer_service_email
        ?? null;

    $storePhone = $store->phone
        ?? $store->telephone
        ?? $store->customer_service_phone
        ?? null;

    $storeVat = $store->vat_number
        ?? $store->piva
        ?? $store->vat
        ?? null;

    $documentsUrl = Route::has('storefront.account.documents.index')
        ? route('storefront.account.documents.index')
        : url('/account/documents');
@endphp

<div class="storefront-topbar bg-dark text-white py-2 small">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2">
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
                <a href="{{ route('storefront.catalog.index') }}" class="text-white-50 text-decoration-none">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>
                    Catalogo
                </a>
            @endif

            @auth('customer')
                @if(Route::has('storefront.wishlist.index'))
                    <a href="{{ route('storefront.wishlist.index') }}" class="text-white-50 text-decoration-none">
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
                    <a href="{{ route('storefront.account.index') }}" class="text-white-50 text-decoration-none">
                        <i class="fa-solid fa-user me-1"></i>
                        Area cliente
                    </a>
                @endif

                @if(Route::has('storefront.logout'))
                    <form method="POST" action="{{ route('storefront.logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-link btn-sm text-white-50 text-decoration-none p-0 align-baseline">
                            <i class="fa-solid fa-right-from-bracket me-1"></i>
                            Logout
                        </button>
                    </form>
                @endif
            @else
                @if(Route::has('storefront.login'))
                    <a href="{{ route('storefront.login') }}" class="text-white-50 text-decoration-none">
                        <i class="fa-solid fa-right-to-bracket me-1"></i>
                        Accedi
                    </a>
                @endif
            @endauth
        </div>
    </div>
</div>