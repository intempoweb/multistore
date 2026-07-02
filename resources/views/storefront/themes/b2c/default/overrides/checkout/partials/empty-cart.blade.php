<div class="card border-0 shadow-sm">
    <div class="card-body py-5 text-center">
        <div class="mb-3 text-muted">
            <i class="fa-solid fa-cart-shopping fa-2x"></i>
        </div>

        <h5 class="mb-2">{{ __('themes_b2c.checkout.empty_cart') }}</h5>
        <p class="text-muted mb-4">{{ __('themes_b2c.cart.add_products') }}</p>

        <a href="{{ route('storefront.catalog.index', $contextParams ?? []) }}" class="btn btn-primary">
            {{ __('themes_b2c.cart.view_catalog') }}
        </a>
    </div>
</div>