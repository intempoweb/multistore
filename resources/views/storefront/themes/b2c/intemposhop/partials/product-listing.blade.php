@if($childrenCategories->isNotEmpty())
    <nav class="intempo-b2c-subcategory-nav" aria-label="{{ __('Sottocategorie') }}">
        @foreach($childrenCategories as $childCategory)
            @if(!empty($childCategory['slug']))
                <a href="{{ route('storefront.category.show', array_merge(['slug' => $childCategory['slug']], $contextParams)) }}">
                    <span>{{ $childCategory['label'] ?? $childCategory['code'] ?? __('Categoria') }}</span>
                    <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                </a>
            @endif
        @endforeach
    </nav>
@endif

<div class="intempo-b2c-listing-layout {{ $hasSidebar ? '' : 'is-full-width' }}">
    @if($hasSidebar)
        <aside class="intempo-b2c-listing-sidebar storefront-sidebar-wrapper">
            @includeIf('storefront.base.partials.sidebar', [
                'sidebarContext' => $listingContext,
                'sidebarTitle' => __('Filtra per'),
                'slug' => $slug ?? null,
                'childrenCategories' => collect(),
                'filterFacets' => $filterFacets,
                'activeFilters' => $activeFilters,
                'hasActiveFilters' => $hasActiveFilters,
                'hideEmptyFilterPanel' => true,
                'sidebarActionUrl' => $listingActionUrl,
                'sidebarResetUrl' => $listingResetUrl,
                'contextParams' => $contextParams,
                'agentContextId' => $agentContextId,
            ])
        </aside>
    @endif

    <section class="intempo-b2c-listing-results storefront-product-results">
        <div class="intempo-b2c-listing-toolbar">
            <div class="intempo-b2c-listing-count">
                <strong>{{ trans_choice(':count prodotto|:count prodotti', $productsTotal, ['count' => $productsTotal]) }}</strong>
                @if($hasActiveFilters)<small>{{ __('Filtri attivi applicati') }}</small>@endif
            </div>

            <form method="GET" action="{{ $listingActionUrl }}" class="intempo-b2c-listing-controls">
                @foreach($baseQuery as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach

                <div class="intempo-b2c-grid-control" role="group" aria-label="{{ __('Prodotti per riga') }}">
                    @foreach([2, 3, 4] as $gridOption)
                        <button type="submit" name="grid" value="{{ $gridOption }}" class="{{ $grid === $gridOption ? 'is-active' : '' }}" title="{{ __(':count prodotti per riga', ['count' => $gridOption]) }}">
                            <i data-lucide="grid-{{ $gridOption === 2 ? '2x2' : '3x3' }}" aria-hidden="true"></i><span class="visually-hidden">{{ $gridOption }}</span>
                        </button>
                    @endforeach
                </div>

                <label for="intempo_b2c_listing_sort" class="visually-hidden">{{ __('Ordina prodotti') }}</label>
                <select name="sort" id="intempo_b2c_listing_sort" onchange="this.form.submit()">
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
            <div class="intempo-b2c-empty-state">{{ __('Nessun prodotto disponibile.') }}</div>
        @else
            <div class="row g-3 g-xl-4">
                @foreach($listingRows as $listingRow)
                    <div class="{{ $ciakProductColClass }}">
                        @include('storefront.base.partials.product-card', [
                            'product' => $listingRow['product'],
                            'listingCard' => $listingRow['listingCard'],
                            'contextParams' => $contextParams,
                            'agentContextId' => $agentContextId,
                        ])
                    </div>
                @endforeach
            </div>
            @if(method_exists($products, 'links'))<div class="intempo-b2c-pagination">{{ $products->appends($paginationQuery)->links('pagination::bootstrap-5') }}</div>@endif
        @endif
    </section>
</div>
