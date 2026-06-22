<div class="p-3">
  <div class="d-flex align-items-center gap-2 mb-3 sidebar-brand">
    <i class="fa-solid fa-gauge-high fs-4"></i>
    <div>
      <div class="fw-semibold">Admin</div>
      <small class="text-white-50">Control Panel</small>
    </div>
  </div>

  <div class="text-white-50 small mb-2">
    Logged: <span class="text-white">{{ auth()->user()->name ?? 'Guest' }}</span>
  </div>

  <hr class="border-secondary">

  @php
    $isCatalogOpen = request()->routeIs('admin.catalog.*')
        || request()->routeIs('admin.products.*')
        || request()->routeIs('admin.attributes.*')
        || request()->routeIs('admin.attribute-values.*');

    $isOrdersOpen = request()->routeIs('admin.orders.*');

    $isCustomersOpen = request()->routeIs('admin.customers.*')
        || request()->routeIs('admin.store-visible-groups.*')
        || request()->routeIs('admin.customer-visible-groups.*');

    $isMarketingOpen = request()->routeIs('admin.promotions.*')
        || request()->routeIs('admin.coupons.*');

    $isCmsOpen = request()->routeIs('admin.storefront-pages.*')
        || request()->routeIs('admin.storefront-seo.*');

    $isShippingOpen = request()->routeIs('admin.shipping-rules.*');

    $isSyncOpen = request()->routeIs('admin.erp-sync.*');
  @endphp

  <ul class="nav nav-pills flex-column gap-1">

    @include('admin.partials.nav-link', [
      'route' => 'admin.dashboard',
      'icon'  => 'fa-solid fa-house',
      'label' => 'Dashboard'
    ])

    @if(Route::has('admin.orders.index'))
      <li class="nav-item">
        <a class="nav-link text-white d-flex align-items-center justify-content-between"
           data-bs-toggle="collapse"
           href="#navOrders"
           role="button"
           aria-expanded="{{ $isOrdersOpen ? 'true' : 'false' }}"
           aria-controls="navOrders">
          <span><i class="fa-solid fa-receipt me-2"></i> Ordini</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </a>

        <div class="collapse {{ $isOrdersOpen ? 'show' : '' }}" id="navOrders">
          <ul class="nav flex-column ms-3 mt-1 gap-1">
            @include('admin.partials.nav-link', [
              'route' => 'admin.orders.index',
              'icon'  => 'fa-solid fa-list',
              'label' => 'Lista ordini'
            ])
          </ul>
        </div>
      </li>
    @endif

    <li class="nav-item">
      <a class="nav-link text-white d-flex align-items-center justify-content-between"
         data-bs-toggle="collapse"
         href="#navCatalog"
         role="button"
         aria-expanded="{{ $isCatalogOpen ? 'true' : 'false' }}"
         aria-controls="navCatalog">
        <span><i class="fa-solid fa-boxes-stacked me-2"></i> Catalogo</span>
        <i class="fa-solid fa-chevron-down small"></i>
      </a>

      <div class="collapse {{ $isCatalogOpen ? 'show' : '' }}" id="navCatalog">
        <ul class="nav flex-column ms-3 mt-1 gap-1">
          @include('admin.partials.nav-link', [
            'route' => 'admin.catalog.index',
            'icon'  => 'fa-solid fa-sitemap',
            'label' => 'Catalogo ERP'
          ])

          @include('admin.partials.nav-link', [
            'route' => 'admin.products.index',
            'icon'  => 'fa-solid fa-box',
            'label' => 'Prodotti'
          ])

          @if(Route::has('admin.attributes.index'))
            @include('admin.partials.nav-link', [
              'route' => 'admin.attributes.index',
              'icon'  => 'fa-solid fa-tags',
              'label' => 'Attributi'
            ])
          @endif

          @if(Route::has('admin.attribute-values.index'))
            @include('admin.partials.nav-link', [
              'route' => 'admin.attribute-values.index',
              'icon'  => 'fa-solid fa-list',
              'label' => 'Valori attributo'
            ])
          @endif
        </ul>
      </div>
    </li>

    @if(Route::has('admin.storefront-pages.index'))
      <li class="nav-item">
        <a class="nav-link text-white d-flex align-items-center justify-content-between"
           data-bs-toggle="collapse"
           href="#navCms"
           role="button"
           aria-expanded="{{ $isCmsOpen ? 'true' : 'false' }}"
           aria-controls="navCms">
          <span><i class="fa-solid fa-layer-group me-2"></i> CMS Storefront</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </a>

        <div class="collapse {{ $isCmsOpen ? 'show' : '' }}" id="navCms">
          <ul class="nav flex-column ms-3 mt-1 gap-1">
            @include('admin.partials.nav-link', [
              'route' => 'admin.storefront-pages.index',
              'icon'  => 'fa-solid fa-file-lines',
              'label' => 'Pagine'
            ])

            @if(Route::has('admin.storefront-pages.create'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.storefront-pages.create',
                'icon'  => 'fa-solid fa-plus',
                'label' => 'Nuova pagina'
              ])
            @endif

            @if(Route::has('admin.storefront-seo.index'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.storefront-seo.index',
                'icon'  => 'fa-solid fa-magnifying-glass-chart',
                'label' => 'SEO catalogo'
              ])
            @endif
          </ul>
        </div>
      </li>
    @endif

    @if(Route::has('admin.promotions.index') || Route::has('admin.coupons.index'))
      <li class="nav-item">
        <a class="nav-link text-white d-flex align-items-center justify-content-between"
           data-bs-toggle="collapse"
           href="#navMarketing"
           role="button"
           aria-expanded="{{ $isMarketingOpen ? 'true' : 'false' }}"
           aria-controls="navMarketing">
          <span><i class="fa-solid fa-percent me-2"></i> Marketing</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </a>

        <div class="collapse {{ $isMarketingOpen ? 'show' : '' }}" id="navMarketing">
          <ul class="nav flex-column ms-3 mt-1 gap-1">
            @if(Route::has('admin.promotions.index'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.promotions.index',
                'icon'  => 'fa-solid fa-percent',
                'label' => 'Promozioni'
              ])
            @endif

            @if(Route::has('admin.promotions.create'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.promotions.create',
                'icon'  => 'fa-solid fa-plus',
                'label' => 'Nuova promozione'
              ])
            @endif

            @if(Route::has('admin.coupons.index'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.coupons.index',
                'icon'  => 'fa-solid fa-ticket',
                'label' => 'Coupon'
              ])
            @endif

            @if(Route::has('admin.coupons.create'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.coupons.create',
                'icon'  => 'fa-solid fa-plus',
                'label' => 'Nuovo coupon'
              ])
            @endif
          </ul>
        </div>
      </li>
    @endif

    @if(Route::has('admin.customers.index') || Route::has('admin.store-visible-groups.index') || Route::has('admin.customer-visible-groups.index'))
      <li class="nav-item">
        <a class="nav-link text-white d-flex align-items-center justify-content-between"
           data-bs-toggle="collapse"
           href="#navCommercial"
           role="button"
           aria-expanded="{{ $isCustomersOpen ? 'true' : 'false' }}"
           aria-controls="navCommercial">
          <span><i class="fa-solid fa-users me-2"></i> Commerciale</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </a>

        <div class="collapse {{ $isCustomersOpen ? 'show' : '' }}" id="navCommercial">
          <ul class="nav flex-column ms-3 mt-1 gap-1">
            @if(Route::has('admin.customers.index'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.customers.index',
                'icon'  => 'fa-solid fa-users',
                'label' => 'Clienti'
              ])
            @endif

            @if(Route::has('admin.store-visible-groups.index'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.store-visible-groups.index',
                'icon'  => 'fa-solid fa-store',
                'label' => 'Gruppi visibili store'
              ])
            @endif

            @if(Route::has('admin.customer-visible-groups.index'))
              @include('admin.partials.nav-link', [
                'route' => 'admin.customer-visible-groups.index',
                'icon'  => 'fa-solid fa-users-viewfinder',
                'label' => 'Gruppi visibili clienti'
              ])
            @endif
          </ul>
        </div>
      </li>
    @endif

    @if(Route::has('admin.shipping-rules.index'))
      <li class="nav-item">
        <a class="nav-link text-white d-flex align-items-center justify-content-between"
           data-bs-toggle="collapse"
           href="#navShipping"
           role="button"
           aria-expanded="{{ $isShippingOpen ? 'true' : 'false' }}"
           aria-controls="navShipping">
          <span><i class="fa-solid fa-truck me-2"></i> Spedizioni</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </a>

        <div class="collapse {{ $isShippingOpen ? 'show' : '' }}" id="navShipping">
          <ul class="nav flex-column ms-3 mt-1 gap-1">
            @include('admin.partials.nav-link', [
              'route' => 'admin.shipping-rules.index',
              'icon'  => 'fa-solid fa-list-check',
              'label' => 'Regole spedizione'
            ])

            @include('admin.partials.nav-link', [
              'route' => 'admin.shipping-rules.create',
              'icon'  => 'fa-solid fa-plus',
              'label' => 'Nuova regola'
            ])
          </ul>
        </div>
      </li>
    @endif

    @if(Route::has('admin.erp-sync.index'))
      <li class="nav-item">
        <a class="nav-link text-white d-flex align-items-center justify-content-between"
           data-bs-toggle="collapse"
           href="#navSync"
           role="button"
           aria-expanded="{{ $isSyncOpen ? 'true' : 'false' }}"
           aria-controls="navSync">
          <span><i class="fa-solid fa-rotate me-2"></i> ERP Sync / Export</span>
          <i class="fa-solid fa-chevron-down small"></i>
        </a>

        <div class="collapse {{ $isSyncOpen ? 'show' : '' }}" id="navSync">
          <ul class="nav flex-column ms-3 mt-1 gap-1">
            @include('admin.partials.nav-link', [
              'route' => 'admin.erp-sync.index',
              'icon'  => 'fa-solid fa-terminal',
              'label' => 'Sync / Export ERP'
            ])
          </ul>
        </div>
      </li>
    @endif

    <hr class="border-secondary my-2">
  </ul>
</div>
