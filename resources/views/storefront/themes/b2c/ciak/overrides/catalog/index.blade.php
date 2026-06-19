{{-- resources/views/storefront/themes/b2c/ciak/overrides/catalog/index.blade.php --}}
@extends($storefrontLayout)

@section('title', ($store->name ?? 'CIAK') . ' - Shop')

@section('content')
@php
    $categories = collect($categories ?? []);
    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $filterFacets = collect($filterFacets ?? []);
    $activeFilters = collect($activeFilters ?? []);

    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $catalogIndexUrl = route('storefront.catalog.index', $contextParams);

    $grid = (int) request('grid', 4);
    $grid = in_array($grid, [2, 3, 4], true) ? $grid : 4;

    $productColClass = match ($grid) {
        2 => 'col-12 col-md-6',
        3 => 'col-12 col-sm-6 col-xl-4',
        default => 'col-12 col-sm-6 col-lg-4 col-xxl-3',
    };

    $currentSort = $currentSort ?? request('sort', 'default');
    $baseQuery = request()->except(['page', 'grid', 'sort']);

    if ($agentContextId !== '') {
        $baseQuery['agent_context'] = $agentContextId;
    }

    $hasActiveFilters = $activeFilters
        ->flatMap(fn ($values) => is_array($values) ? $values : [$values])
        ->filter(fn ($value) => trim((string) $value) !== '')
        ->isNotEmpty();

    $productsTotal = $products?->total() ?? 0;
@endphp

<div class="ciak-listing-page storefront-category-page" data-storefront-category-page>
    <header class="ciak-listing-header">
        <div>
            <span class="ciak-kicker">Shop</span>
            <h1>Catalogo CIAK</h1>
            <p>Agende, taccuini e accessori disponibili nello shop.</p>
        </div>

        <strong>{{ number_format($productsTotal, 0, ',', '.') }} prodotti</strong>
    </header>

    <div class="ciak-listing-layout">
        <div class="ciak-listing-sidebar storefront-sidebar-wrapper">
            @includeIf('storefront.base.partials.sidebar', [
                'sidebarContext' => 'catalog',
                'sidebarTitle' => 'Filtra',
                'slug' => null,
                'childrenCategories' => $categories,
                'filterFacets' => $filterFacets,
                'activeFilters' => $activeFilters,
                'hasActiveFilters' => $hasActiveFilters,
                'sidebarActionUrl' => $catalogIndexUrl,
                'sidebarResetUrl' => $catalogIndexUrl,
                'contextParams' => $contextParams,
                'agentContextId' => $agentContextId,
                'emptyFiltersMessage' => 'Nessun filtro disponibile per questa selezione.',
            ])
        </div>

        <section class="ciak-listing-results storefront-product-results">
            <div class="ciak-listing-toolbar">
                <div>
                    <h2>Prodotti</h2>

                    @if($hasActiveFilters)
                        <small>Filtri attivi applicati</small>
                    @else
                        <small>Mostriamo solo articoli disponibili.</small>
                    @endif
                </div>

                <form method="GET" action="{{ $catalogIndexUrl }}" class="ciak-listing-controls">
                    @foreach($baseQuery as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $item)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <div class="ciak-grid-control" role="group" aria-label="Prodotti per riga">
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

                    <label for="ciak_catalog_sort" class="visually-hidden">Ordina prodotti</label>
                    <select name="sort" id="ciak_catalog_sort" onchange="this.form.submit()" aria-label="Ordina prodotti">
                        <option value="default" @selected($currentSort === 'default')>Predefinito</option>
                        <option value="newest" @selected($currentSort === 'newest')>Novità</option>
                        <option value="name_asc" @selected($currentSort === 'name_asc')>Nome A-Z</option>
                        <option value="name_desc" @selected($currentSort === 'name_desc')>Nome Z-A</option>
                        <option value="price_asc" @selected($currentSort === 'price_asc')>Prezzo crescente</option>
                        <option value="price_desc" @selected($currentSort === 'price_desc')>Prezzo decrescente</option>
                    </select>
                </form>
            </div>

            @if($products->isEmpty())
                <div class="ciak-empty-state">
                    Nessun prodotto disponibile con i filtri selezionati.
                </div>
            @else
                <div class="row g-3">
                    @foreach($products as $product)
                        @php
                            $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                        @endphp

                        <div class="{{ $productColClass }}">
                            @include('storefront.base.partials.product-card', [
                                'product' => $product,
                                'listingCard' => $listingCard,
                                'contextParams' => $contextParams,
                                'agentContextId' => $agentContextId,
                            ])
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
