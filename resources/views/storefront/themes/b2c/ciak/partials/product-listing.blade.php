@php
    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $total = method_exists($products, 'total') ? $products->total() : collect($products)->count();
@endphp
<div class="ciak-listing-layout">
    <aside class="ciak-listing-sidebar">
        @includeIf('storefront.base.partials.sidebar', [
            'sidebarContext' => $sidebarContext ?? 'category',
            'sidebarTitle' => __('Filtri'),
            'slug' => $slug ?? null,
            'childrenCategories' => $childrenCategories ?? collect(),
            'filterFacets' => $filterFacets ?? collect(),
            'activeFilters' => $activeFilters ?? collect(),
            'hasActiveFilters' => collect($activeFilters ?? [])->flatten()->filter()->isNotEmpty(),
            'sidebarActionUrl' => $sidebarActionUrl ?? url()->current(),
            'sidebarResetUrl' => $sidebarResetUrl ?? url()->current(),
        ])
    </aside>
    <section class="ciak-listing-results">
        <div class="ciak-listing-toolbar"><span>{{ trans_choice(':count prodotto|:count prodotti', $total, ['count' => $total]) }}</span><form method="GET"><select name="sort" aria-label="{{ __('Ordina prodotti') }}" onchange="this.form.submit()"><option value="default" @selected(($currentSort ?? 'default') === 'default')>{{ __('Predefinito') }}</option><option value="newest" @selected(($currentSort ?? '') === 'newest')>{{ __('Novità') }}</option><option value="price_asc" @selected(($currentSort ?? '') === 'price_asc')>{{ __('Prezzo crescente') }}</option><option value="price_desc" @selected(($currentSort ?? '') === 'price_desc')>{{ __('Prezzo decrescente') }}</option><option value="name_asc" @selected(($currentSort ?? '') === 'name_asc')>{{ __('Nome A-Z') }}</option></select></form></div>
        @if(collect($products)->isEmpty())
            <p class="ciak-empty-state">{{ __('Nessun prodotto disponibile.') }}</p>
        @else
            <div class="ciak-catalog-grid">
                @foreach($products as $product)
                    @php($listingCard = collect($listingCardsByProductSku->get((string) $product->sku, [])))
                    @include('storefront.base.partials.product-card', ['product' => $product, 'listingCard' => $listingCard])
                @endforeach
            </div>
            @if(method_exists($products, 'links'))<div class="ciak-pagination">{{ $products->appends(request()->query())->links('pagination::bootstrap-5') }}</div>@endif
        @endif
    </section>
</div>
