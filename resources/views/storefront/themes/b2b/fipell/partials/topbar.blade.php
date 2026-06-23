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
                    <span class="fipell-topbar-eyebrow">Portale</span>
                    <span class="fipell-topbar-store-name">{{ $storeName }}</span>
                </div>
            </div>

            <div class="fipell-topbar-contacts d-none d-xl-flex">
                @if($storeEmail)
                    <a href="mailto:{{ $storeEmail }}">
                        <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                        <span>{{ $storeEmail }}</span>
                    </a>
                @endif

                @if($storePhone)
                    <a href="tel:{{ preg_replace('/\s+/', '', (string) $storePhone) }}">
                        <i class="fa-solid fa-phone" aria-hidden="true"></i>
                        <span>{{ $storePhone }}</span>
                    </a>
                @endif

                @if($storeVat)
                    <span>
                        <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                        <span>P. IVA {{ $storeVat }}</span>
                    </span>
                @endif
            </div>

            <div class="fipell-topbar-links">
                @if(Route::has('storefront.catalog.index'))
                    <a href="{{ route('storefront.catalog.index', $contextParams) }}">
                        <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
                        <span>Catalogo</span>
                    </a>
                @endif

                @auth('customer')
                    @if(Route::has('storefront.wishlist.index'))
                        <a href="{{ route('storefront.wishlist.index', $contextParams) }}">
                            <i class="fa-regular fa-heart" aria-hidden="true"></i>
                            <span>Preferiti</span>
                        </a>
                    @endif

                    @if($isB2b)
                        <a href="{{ $documentsUrl }}">
                            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                            <span>Documenti</span>
                        </a>
                    @endif

                    @if(Route::has('storefront.account.index'))
                        <a href="{{ route('storefront.account.index', $contextParams) }}">
                            <i class="fa-regular fa-user" aria-hidden="true"></i>
                            <span>Area cliente</span>
                        </a>
                    @endif

                    @if(Route::has('storefront.logout'))
                        <a href="{{ route('storefront.logout') }}">
                            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                            <span>Logout</span>
                        </a>
                    @endif
                @else
                    @if(Route::has('storefront.login'))
                        <a href="{{ route('storefront.login', $contextParams) }}" class="fipell-topbar-login">
                            <span>Accedi</span>
                        </a>
                    @endif
                @endauth
            </div>

        </div>
    </div>
</div>
