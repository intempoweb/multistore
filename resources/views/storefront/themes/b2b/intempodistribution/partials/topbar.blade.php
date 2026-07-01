<div class="storefront-topbar intempo-b2b-topbar">
    <div class="container-fluid intempo-b2b-shell">
        <div class="intempo-b2b-topbar-inner">
            <div class="intempo-b2b-topbar-left"></div>

            <div class="intempo-b2b-topbar-message">
                <i class="fa-solid fa-truck-fast" aria-hidden="true"></i>
                <span>Porto franco per ordini pari o superiori a 300 €</span>
            </div>

            <div class="intempo-b2b-topbar-links">
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
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                            <span>Logout</span>
                        </a>
                    @endif
                @else
                    @if(Route::has('storefront.login'))
                        <a href="{{ route('storefront.login', $contextParams) }}">
                            <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
                            <span>Accedi</span>
                        </a>
                    @endif
                @endauth
            </div>
        </div>
    </div>
</div>
