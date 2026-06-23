@extends($storefrontLayout)

@section('title', ($store->name ?? 'Fipell') . ' - Catalogo')

@section('content')
<div class="fipell-catalog-page storefront-category-page" data-storefront-category-page>
    <header class="fipell-catalog-header">
        <div>
            <span>Catalogo B2B</span>
            <h1>Catalogo prodotti</h1>
            <p>Consulta tutti gli articoli disponibili per il tuo account con filtri, disponibilita e prezzi cliente.</p>
        </div>

        <strong>{{ number_format($productsTotal, 0, ',', '.') }} prodotti</strong>
    </header>

    <div class="row g-4">
        <div class="col-12 col-lg-3">
            <div class="storefront-sidebar-wrapper">
                @includeIf('storefront.base.partials.sidebar', [
                    'sidebarContext' => 'catalog',
                    'sidebarTitle' => 'Filtri',
                    'slug' => null,
                    'childrenCategories' => $categories,
                    'filterFacets' => $filterFacets,
                    'activeFilters' => $activeFilters,
                    'hasActiveFilters' => $hasActiveFilters,
                    'sidebarActionUrl' => $listingActionUrl,
                    'sidebarResetUrl' => $listingResetUrl,
                    'contextParams' => $contextParams,
                    'agentContextId' => $agentContextId,
                    'emptyFiltersMessage' => 'Nessun attributo filtrabile disponibile sui prodotti visibili per questo account.',
                ])
            </div>
        </div>

        <div class="col-12 col-lg-9">
            <div class="storefront-product-results">
                <section class="fipell-catalog-results">
                    <div class="fipell-catalog-toolbar">
                        <div>
                            <h2>Prodotti disponibili</h2>

                            @if($hasActiveFilters)
                                <small>Filtri attivi applicati</small>
                            @else
                                <small>Mostriamo il catalogo completo visibile per questo cliente.</small>
                            @endif
                        </div>

                        <form method="GET" action="{{ $listingActionUrl }}" class="fipell-catalog-controls">
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

                            <div class="fipell-catalog-grid-control" role="group" aria-label="Prodotti per riga">
                                @foreach([2, 3, 4] as $gridOption)
                                    <button
                                        type="submit"
                                        name="grid"
                                        value="{{ $gridOption }}"
                                        class="{{ $grid === $gridOption ? 'is-active' : '' }}"
                                        title="{{ $gridOption }} prodotti per riga"
                                    >
                                        {{ $gridOption }}
                                    </button>
                                @endforeach
                            </div>

                            <label for="fipell_catalog_sort" class="visually-hidden">Ordina prodotti</label>
                            <select
                                name="sort"
                                id="fipell_catalog_sort"
                                onchange="this.form.submit()"
                                aria-label="Ordina prodotti"
                            >
                                <option value="default" @selected($currentSort === 'default')>Predefinito</option>
                                <option value="newest" @selected($currentSort === 'newest')>Novita</option>
                                <option value="name_asc" @selected($currentSort === 'name_asc')>Nome A-Z</option>
                                <option value="name_desc" @selected($currentSort === 'name_desc')>Nome Z-A</option>
                                <option value="sku_asc" @selected($currentSort === 'sku_asc')>SKU crescente</option>
                                <option value="sku_desc" @selected($currentSort === 'sku_desc')>SKU decrescente</option>
                                <option value="price_asc" @selected($currentSort === 'price_asc')>Prezzo crescente</option>
                                <option value="price_desc" @selected($currentSort === 'price_desc')>Prezzo decrescente</option>
                            </select>
                        </form>
                    </div>

                    <div class="fipell-catalog-body">
                        @if($products->isEmpty())
                            <div class="fipell-home-empty">
                                Nessun prodotto disponibile con i filtri selezionati.
                            </div>
                        @else
                            <div class="row g-3">
                                @foreach($listingRows as $listingRow)
                                    <div class="{{ $productColClass }}">
                                        @include('storefront.base.partials.product-card', [
                                            'product' => $listingRow['product'],
                                            'listingCard' => $listingRow['listingCard'],
                                            'contextParams' => $contextParams,
                                            'agentContextId' => $agentContextId,
                                        ])
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4">
                                {{ $products->appends($paginationQuery)->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
