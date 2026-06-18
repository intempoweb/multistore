@php
    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $storeName = $store->name ?? config('app.name', 'Store');
    $storeLogo = $store?->logo_url ? media_url($store->logo_url) : null;
    $isB2b = (bool) ($store?->is_b2b ?? false);

    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $contextParams = $contextParams ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);

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
        ? route('storefront.account.documents.index', $contextParams)
        : url('/account/documents');
@endphp

<div class="storefront-topbar fipell-topbar py-2 small">
    <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a
                href="{{ route('storefront.home', $contextParams) }}"
                class="fipell-topbar-brand d-inline-flex align-items-center text-decoration-none"
                aria-label="{{ $storeName }}"
            >
                @if($storeLogo)
                    <img
                        src="{{ $storeLogo }}"
                        alt="{{ $storeName }}"
                        class="fipell-topbar-logo"
                        loading="eager"
                        decoding="async"
                    >
                @else
                    <span class="fipell-topbar-logo-fallback d-inline-flex align-items-center justify-content-center rounded">
                        {{ mb_substr($storeName, 0, 1) }}
                    </span>
                @endif
            </a>

            @if($storeEmail)
                <a href="mailto:{{ $storeEmail }}" class="text-body-secondary text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-envelope"></i>
                    <span>{{ $storeEmail }}</span>
                </a>
            @endif

            @if($storePhone)
                <a href="tel:{{ preg_replace('/\s+/', '', (string) $storePhone) }}" class="text-body-secondary text-decoration-none d-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-phone"></i>
                    <span>{{ $storePhone }}</span>
                </a>
            @endif

            @if($storeVat)
                <span class="text-body-secondary d-inline-flex align-items-center gap-1">
                    <i class="fa-solid fa-receipt"></i>
                    <span>P. IVA {{ $storeVat }}</span>
                </span>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            @if(Route::has('storefront.catalog.index'))
                <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="text-body-secondary text-decoration-none">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>
                    Catalogo
                </a>
            @endif

            @auth('customer')
                @if(Route::has('storefront.wishlist.index'))
                    <a href="{{ route('storefront.wishlist.index', $contextParams) }}" class="text-body-secondary text-decoration-none">
                        <i class="fa-regular fa-heart me-1"></i>
                        Preferiti
                    </a>
                @endif

                @if($isB2b)
                    <a href="{{ $documentsUrl }}" class="text-body-secondary text-decoration-none">
                        <i class="fa-solid fa-file-lines me-1"></i>
                        Area documentale
                    </a>
                @endif

                @if(Route::has('storefront.account.index'))
                    <a href="{{ route('storefront.account.index', $contextParams) }}" class="text-body-secondary text-decoration-none">
                        <i class="fa-solid fa-user me-1"></i>
                        Area cliente
                    </a>
                @endif

                @if(Route::has('storefront.logout'))
                    <a href="{{ route('storefront.logout') }}" class="text-body-secondary text-decoration-none">
                        <i class="fa-solid fa-right-from-bracket me-1"></i>
                        Logout
                    </a>
                @endif
            @else
                @if(Route::has('storefront.login'))
                    <a href="{{ route('storefront.login', $contextParams) }}" class="text-body-secondary text-decoration-none">
                        <i class="fa-solid fa-right-to-bracket me-1"></i>
                        Accedi
                    </a>
                @endif
            @endauth
        </div>
    </div>
</div>