@php
    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $childrenCategories = collect($childrenCategories ?? []);
    $filterFacets = collect($filterFacets ?? []);
    $activeFilters = collect($activeFilters ?? []);
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $listingContext = $sidebarContext ?? 'category';
    $listingActionUrl = $sidebarActionUrl ?? url()->current();
    $listingResetUrl = $sidebarResetUrl ?? url()->current();
    $currentSort = $currentSort ?? request('sort', 'default');
    $grid = (int) request('grid', 4);
    $grid = in_array($grid, [2, 3, 4], true) ? $grid : 4;
    $productColClass = match ($grid) {
        2 => 'col-12 col-md-6',
        3 => 'col-12 col-sm-6 col-xl-4',
        default => 'col-12 col-sm-6 col-lg-4 col-xxl-3',
    };
    $total = method_exists($products, 'total') ? $products->total() : collect($products)->count();
    $hasActiveFilters = $activeFilters->flatten()->filter(fn ($value) => filled($value))->isNotEmpty();
    $hasSidebar = $childrenCategories->isNotEmpty() || $filterFacets->isNotEmpty() || $hasActiveFilters;
    $baseQuery = request()->except(['page', 'grid', 'sort']);
    if ($agentContextId !== '') $baseQuery['agent_context'] = $agentContextId;
@endphp

@if($childrenCategories->isNotEmpty())
    <nav class="ciak-subcategory-nav" aria-label="{{ __('Sottocategorie') }}">
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

<div class="ciak-listing-layout {{ $hasSidebar ? '' : 'is-full-width' }}">
    @if($hasSidebar)
        <aside class="ciak-listing-sidebar storefront-sidebar-wrapper">
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

    <section class="ciak-listing-results storefront-product-results">
        <div class="ciak-listing-toolbar">
            <div class="ciak-listing-count">
                <strong>{{ trans_choice(':count prodotto|:count prodotti', $total, ['count' => $total]) }}</strong>
                @if($hasActiveFilters)<small>{{ __('Filtri attivi applicati') }}</small>@endif
            </div>

            <form method="GET" action="{{ $listingActionUrl }}" class="ciak-listing-controls">
                @foreach($baseQuery as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach

                <div class="ciak-grid-control" role="group" aria-label="{{ __('Prodotti per riga') }}">
                    @foreach([2, 3, 4] as $gridOption)
                        <button type="submit" name="grid" value="{{ $gridOption }}" class="{{ $grid === $gridOption ? 'is-active' : '' }}" title="{{ __(':count prodotti per riga', ['count' => $gridOption]) }}">
                            <i data-lucide="grid-{{ $gridOption === 2 ? '2x2' : '3x3' }}" aria-hidden="true"></i><span class="visually-hidden">{{ $gridOption }}</span>
                        </button>
                    @endforeach
                </div>

                <label for="ciak_listing_sort" class="visually-hidden">{{ __('Ordina prodotti') }}</label>
                <select name="sort" id="ciak_listing_sort" onchange="this.form.submit()">
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
            <div class="ciak-empty-state">{{ __('Nessun prodotto disponibile.') }}</div>
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
            @if(method_exists($products, 'links'))<div class="ciak-pagination">{{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}</div>@endif
        @endif
    </section>
</div>
