@extends($storefrontLayout)

@section('title', ($store->name ?? 'Store') . ' - Prodotti')

@section('content')
<div class="row g-4 storefront-category-page" data-storefront-category-page>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="text-muted small mb-1 text-uppercase">
                    Catalogo B2B
                </div>

                <h1 class="h3 fw-bold mb-2">
                    Tutti i prodotti
                </h1>

                <div class="text-muted small">
                    Visualizza tutti i prodotti disponibili per il tuo account.
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-3">
        <div class="storefront-sidebar-wrapper">
            @includeIf('storefront.base.partials.sidebar', [
                'sidebarContext' => 'home-products',
                'sidebarTitle' => 'Filtri',
                'slug' => null,
                'childrenCategories' => $childrenCategories,
                'filterFacets' => $filterFacets,
                'activeFilters' => $activeFilters,
                'hasActiveFilters' => $hasActiveFilters,
                'sidebarActionUrl' => $homeProductsActionUrl,
                'sidebarResetUrl' => $homeProductsActionUrl,
                'emptyFiltersMessage' => 'Nessun attributo filtrabile disponibile sui prodotti visibili per questo account.',
            ])
        </div>
    </div>

    <div class="col-12 col-lg-9">
        <div class="storefront-product-results">

            <div class="card border-0 shadow-sm">

                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-semibold">
                            Prodotti disponibili
                        </div>

                        @if($hasActiveFilters)
                            <div class="small text-muted">
                                Filtri attivi applicati
                            </div>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">

                        <div class="small text-muted me-1">
                            {{ $productsTotal }} prodotti
                        </div>

                        <form method="GET" action="{{ $homeProductsActionUrl }}" class="d-flex align-items-center gap-3 flex-wrap">

                            @foreach($baseQuery as $key => $value)
                                @if(is_array($value))
                                    @foreach($value as $item)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach

                            <input type="hidden" name="grid" value="{{ $grid }}" data-grid-input>

                            <div class="d-flex align-items-center gap-2">
                                <span class="small text-muted fw-semibold">
                                    Vista
                                </span>

                                <div class="btn-group btn-group-sm" role="group" aria-label="Prodotti per riga">
                                    @foreach([2, 3, 4] as $gridOption)
                                        <button
                                            type="submit"
                                            name="grid"
                                            value="{{ $gridOption }}"
                                            class="btn {{ $grid === $gridOption ? 'btn-dark' : 'btn-outline-secondary' }}"
                                            title="{{ $gridOption }} prodotti per riga"
                                        >
                                            <i class="fa-solid fa-grip me-1"></i>
                                            {{ $gridOption }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <label for="home_products_sort" class="small text-muted fw-semibold mb-0">
                                    Ordina
                                </label>

                                <select
                                    name="sort"
                                    id="home_products_sort"
                                    class="form-select form-select-sm border-0 bg-light shadow-none rounded-pill px-3"
                                    onchange="this.form.submit()"
                                    aria-label="Ordina prodotti"
                                    style="min-width: 170px;"
                                >
                                    <option value="default" @selected($currentSort === 'default')>
                                        Predefinito
                                    </option>

                                    <option value="newest" @selected($currentSort === 'newest')>
                                        Novità
                                    </option>

                                    <option value="name_asc" @selected($currentSort === 'name_asc')>
                                        Nome A-Z
                                    </option>

                                    <option value="name_desc" @selected($currentSort === 'name_desc')>
                                        Nome Z-A
                                    </option>

                                    <option value="sku_asc" @selected($currentSort === 'sku_asc')>
                                        SKU crescente
                                    </option>

                                    <option value="sku_desc" @selected($currentSort === 'sku_desc')>
                                        SKU decrescente
                                    </option>

                                    <option value="price_asc" @selected($currentSort === 'price_asc')>
                                        Prezzo crescente
                                    </option>

                                    <option value="price_desc" @selected($currentSort === 'price_desc')>
                                        Prezzo decrescente
                                    </option>
                                </select>
                            </div>

                        </form>

                    </div>
                </div>

                <div class="card-body">

                    @if($products->isEmpty())

                        <div class="alert alert-light border mb-0">
                            Nessun prodotto disponibile per questo account.
                        </div>

                    @else

                        <div class="row g-3">

                            @foreach($homeProductRows as $row)
                                <div class="{{ $productColClass }}">
                                    @include('storefront.base.partials.product-card', [
                                        'product' => $row['product'],
                                        'listingCard' => $row['listingCard'],
                                    ])
                                </div>
                            @endforeach

                        </div>

                        <div class="mt-4">
                            {{ $products->links('pagination::bootstrap-5') }}
                        </div>

                    @endif

                </div>

            </div>

        </div>
    </div>

</div>

@endsection
