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

<div class="storefront-topbar fipell-topbar">
    <div class="container-fluid fipell-topbar-inner">
        <a
            href="{{ route('storefront.home', $contextParams) }}"
            class="fipell-topbar-brand"
            aria-label="{{ $storeName }}"
        >
            @if($storeLogo)
                <img src="{{ $storeLogo }}" alt="{{ $storeName }}" class="fipell-topbar-logo">
            @else
                <span class="fipell-topbar-logo-fallback">{{ mb_substr($storeName, 0, 1) }}</span>
            @endif
        </a>

        <div class="fipell-topbar-links">
            @auth('customer')
                @if(Route::has('storefront.wishlist.index'))
                    <a href="{{ route('storefront.wishlist.index', $contextParams) }}">Preferiti</a>
                @endif

                @if($isB2b)
                    <a href="{{ $documentsUrl }}">Documenti</a>
                @endif

                @if(Route::has('storefront.account.index'))
                    <a href="{{ route('storefront.account.index', $contextParams) }}">Area cliente</a>
                @endif

                @if(Route::has('storefront.logout'))
                    <a href="{{ route('storefront.logout') }}">Logout</a>
                @endif
            @else
                @if(Route::has('storefront.login'))
                    <a href="{{ route('storefront.login', $contextParams) }}">Accedi</a>
                @endif
            @endauth
        </div>
    </div>
</div>