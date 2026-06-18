@php
    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $storeName = $store->name ?? config('app.name', 'Store');
    $storeLogo = $store?->logo_url ? media_url($store->logo_url) : null;
    $isB2b = (bool) ($store?->is_b2b ?? false);

    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $contextParams = $contextParams ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);

    $storeEmail = $store->email ?? $store->support_email ?? $store->customer_service_email ?? null;
    $storePhone = $store->phone ?? $store->telephone ?? $store->customer_service_phone ?? null;
    $storeVat = $store->vat_number ?? $store->piva ?? $store->vat ?? null;

    $documentsUrl = Route::has('storefront.account.documents.index')
        ? route('storefront.account.documents.index', $contextParams)
        : url('/account/documents');
@endphp

<div class="storefront-topbar fipell-topbar">
    <div class="container-fluid fipell-shell">
        <div class="fipell-topbar-inner">

            <div class="fipell-topbar-brand-area">
                <a href="{{ route('storefront.home', $contextParams) }}" class="fipell-topbar-brand" aria-label="{{ $storeName }}">
                    @if($storeLogo)
                        <img src="{{ $storeLogo }}" alt="{{ $storeName }}" class="fipell-topbar-logo" loading="eager" decoding="async">
                    @else
                        <span class="fipell-logo-fallback">{{ mb_substr($storeName, 0, 1) }}</span>
                    @endif
                </a>

                <div class="fipell-topbar-store-copy d-none d-lg-block">
                    <span class="fipell-topbar-eyebrow">Portale B2B</span>
                    <span class="fipell-topbar-store-name">{{ $storeName }}</span>
                </div>
            </div>

            <div class="fipell-topbar-contacts d-none d-xl-flex">
                @if($storeEmail)
                    <a href="mailto:{{ $storeEmail }}">
                        <i class="fa-solid fa-envelope"></i>
                        <span>{{ $storeEmail }}</span>
                    </a>
                @endif

                @if($storePhone)
                    <a href="tel:{{ preg_replace('/\s+/', '', (string) $storePhone) }}">
                        <i class="fa-solid fa-phone"></i>
                        <span>{{ $storePhone }}</span>
                    </a>
                @endif

                @if($storeVat)
                    <span>
                        <i class="fa-solid fa-receipt"></i>
                        <span>P. IVA {{ $storeVat }}</span>
                    </span>
                @endif
            </div>

            <div class="fipell-topbar-links">
                @if(Route::has('storefront.catalog.index'))
                    <a href="{{ route('storefront.catalog.index', $contextParams) }}">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <span>Catalogo</span>
                    </a>
                @endif

                @auth('customer')
                    @if(Route::has('storefront.wishlist.index'))
                        <a href="{{ route('storefront.wishlist.index', $contextParams) }}">
                            <i class="fa-regular fa-heart"></i>
                            <span>Preferiti</span>
                        </a>
                    @endif

                    @if($isB2b)
                        <a href="{{ $documentsUrl }}">
                            <i class="fa-solid fa-file-lines"></i>
                            <span>Documenti</span>
                        </a>
                    @endif

                    @if(Route::has('storefront.account.index'))
                        <a href="{{ route('storefront.account.index', $contextParams) }}">
                            <i class="fa-solid fa-user"></i>
                            <span>Area cliente</span>
                        </a>
                    @endif

                    @if(Route::has('storefront.logout'))
                        <a href="{{ route('storefront.logout') }}">
                            <i class="fa-solid fa-right-from-bracket"></i>
                            <span>Logout</span>
                        </a>
                    @endif
                @else
                    @if(Route::has('storefront.login'))
                        <a href="{{ route('storefront.login', $contextParams) }}" class="fipell-topbar-login">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <span>Accedi</span>
                        </a>
                    @endif
                @endauth
            </div>

        </div>
    </div>
</div>