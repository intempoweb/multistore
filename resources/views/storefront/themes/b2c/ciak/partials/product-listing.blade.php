@php
    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $filterFacets = collect($filterFacets ?? []);
    $activeFilters = collect($activeFilters ?? []);
    $listingCategories = collect($listingCategories ?? $childrenCategories ?? []);
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $listingActionUrl = $listingActionUrl ?? url()->current();
    $listingResetUrl = $listingResetUrl ?? $listingActionUrl;
    $listingEyebrow = $listingEyebrow ?? __('Shop');
    $listingTitle = $listingTitle ?? __('Prodotti');
    $listingDescription = $listingDescription ?? null;
    $listingResultsTitle = $listingResultsTitle ?? __('Prodotti');
    $listingEmptyMessage = $listingEmptyMessage ?? __('Nessun prodotto disponibile con i filtri selezionati.');
    $currentSort = $currentSort ?? request('sort', 'default');
    $productsTotal = $products?->total() ?? 0;
    $grid = (int) request('grid', 4);
    $grid = in_array($grid, [2, 3, 4], true) ? $grid : 4;
    $productColClass = match ($grid) {
        2 => 'col-12 col-md-6',
        3 => 'col-12 col-sm-6 col-xl-4',
        default => 'col-12 col-sm-6 col-lg-4 col-xxl-3',
    };
    $baseQuery = request()->except(['page', 'grid', 'sort']);

    if ($agentContextId !== '') {
        $baseQuery['agent_context'] = $agentContextId;
    }

    $hasActiveFilters = $activeFilters
        ->flatMap(fn ($values) => is_array($values) ? $values : [$values])
        ->filter(fn ($value) => trim((string) $value) !== '')
        ->isNotEmpty();
    $hasSidebar = $filterFacets->isNotEmpty() || $hasActiveFilters;
@endphp

<div class="ciak-listing-page storefront-category-page" data-storefront-category-page>
    <header class="ciak-listing-header">
        <div>
            <span class="ciak-kicker">{{ $listingEyebrow }}</span>
            <h1>{{ $listingTitle }}</h1>
            @if($listingDescription)
                <p>{{ $listingDescription }}</p>
            @endif
        </div>
        <strong>{{ trans_choice(':count prodotto|:count prodotti', $productsTotal, ['count' => number_format($productsTotal, 0, ',', '.')]) }}</strong>
    </header>

    @if($listingCategories->isNotEmpty())
        <nav class="ciak-subcategory-nav" aria-label="{{ __('Sottocategorie') }}">
            @foreach($listingCategories as $childCategory)
                @if(!empty($childCategory['slug']))
                    <a href="{{ route('storefront.category.show', array_merge(['slug' => $childCategory['slug']], $contextParams)) }}">
                        <span>{{ $childCategory['label'] ?? $childCategory['code'] ?? __('Categoria') }}</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="ciak-listing-layout {{ $hasSidebar ? '' : 'is-full-width' }}">
        @if($hasSidebar)
            <div class="ciak-listing-sidebar storefront-sidebar-wrapper">
                @include('storefront.base.partials.sidebar', [
                    'sidebarContext' => $listingContext ?? 'category',
                    'sidebarTitle' => __('Filtra per'),
                    'slug' => $slug ?? null,
                    'childrenCategories' => collect(),
                    'filterFacets' => $filterFacets,
                    'activeFilters' => $activeFilters,
                    'hideEmptyFilterPanel' => true,
                    'sidebarActionUrl' => $listingActionUrl,
                    'sidebarResetUrl' => $listingResetUrl,
                    'contextParams' => $contextParams,
                    'agentContextId' => $agentContextId,
                ])
            </div>
        @endif

        <section class="ciak-listing-results storefront-product-results">
            <div class="ciak-listing-toolbar">
                <div>
                    <h2>{{ $listingResultsTitle }}</h2>
                    <small>{{ $hasActiveFilters ? __('Filtri attivi applicati') : __('Articoli disponibili online') }}</small>
                </div>

                <form method="GET" action="{{ $listingActionUrl }}" class="ciak-listing-controls">
                    @foreach($baseQuery as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $item)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <div class="ciak-grid-control" role="group" aria-label="{{ __('Prodotti per riga') }}">
                        @foreach([2, 3, 4] as $gridOption)
                            <button
                                type="submit"
                                name="grid"
                                value="{{ $gridOption }}"
                                class="{{ $grid === $gridOption ? 'is-active' : '' }}"
                                title="{{ __(':count prodotti per riga', ['count' => $gridOption]) }}"
                            >
                                <i class="fa-solid fa-grip" aria-hidden="true"></i>
                                <span class="visually-hidden">{{ $gridOption }}</span>
                            </button>
                        @endforeach
                    </div>

                    <label for="ciak_listing_sort" class="visually-hidden">{{ __('Ordina prodotti') }}</label>
                    <select name="sort" id="ciak_listing_sort" onchange="this.form.submit()" aria-label="{{ __('Ordina prodotti') }}">
                        <option value="default" @selected($currentSort === 'default')>{{ __('Predefinito') }}</option>
                        <option value="newest" @selected($currentSort === 'newest')>{{ __('Novità') }}</option>
                        <option value="name_asc" @selected($currentSort === 'name_asc')>{{ __('Nome A-Z') }}</option>
                        <option value="name_desc" @selected($currentSort === 'name_desc')>{{ __('Nome Z-A') }}</option>
                        <option value="price_asc" @selected($currentSort === 'price_asc')>{{ __('Prezzo crescente') }}</option>
                        <option value="price_desc" @selected($currentSort === 'price_desc')>{{ __('Prezzo decrescente') }}</option>
                    </select>
                </form>
            </div>

            @if($products->isEmpty())
                <div class="ciak-empty-state">{{ $listingEmptyMessage }}</div>
            @else
                <div class="row g-3 g-xl-4">
                    @foreach($products as $product)
                        <div class="{{ $productColClass }}">
                            @include('storefront.base.partials.product-card', [
                                'product' => $product,
                                'listingCard' => collect($listingCardsByProductSku->get((string) $product->sku, [])),
                                'contextParams' => $contextParams,
                                'agentContextId' => $agentContextId,
                            ])
                        </div>
                    @endforeach
                </div>

                <div class="mt-5">
                    {{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </section>
    </div>
</div>
