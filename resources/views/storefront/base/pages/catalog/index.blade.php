@extends($storefrontLayout)

@section('title', ($store->name ?? __('themes_b2c.catalog.catalog')) . ' - ' . __('themes_b2c.catalog.catalog'))

@section('content')
<div class="row g-4 storefront-category-page" data-storefront-category-page>

    <div class="col-12">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-4 p-lg-5">
                <div class="text-muted small text-uppercase mb-2">
                    {{ ($store?->isB2B() ?? false) ? __('themes_b2c.catalog.catalog_b2b') : __('themes_b2c.catalog.catalog_store') }}
                </div>

                <h1 class="h2 fw-bold mb-3">
                    {{ __('themes_b2c.catalog.catalog') }} {{ $store->name ?? __('themes_b2c.catalog.store') }}
                </h1>

                <p class="text-secondary mb-0 col-xl-8">
                    {{ __('themes_b2c.catalog.description') }}
                </p>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-3">
        <div class="storefront-sidebar-wrapper">
            @includeIf('storefront.base.partials.sidebar', [
                'sidebarContext' => 'catalog',
                'sidebarTitle' => __('themes_b2c.catalog.categories'),
                'slug' => null,
                'childrenCategories' => $categories,
                'filterFacets' => collect(),
                'activeFilters' => collect(),
                'hasActiveFilters' => false,
                'sidebarActionUrl' => $listingActionUrl,
                'sidebarResetUrl' => $listingResetUrl,
                'contextParams' => $contextParams,
                'agentContextId' => $agentContextId,
                'emptyFiltersMessage' => __('themes_b2c.catalog.empty_filters_message'),
            ])
        </div>
    </div>

    <div class="col-12 col-lg-9">
        <div class="storefront-product-results">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2 class="h5 mb-1">{{ __('themes_b2c.catalog.catalog_categories') }}</h2>
                        <div class="text-muted small">
                            {{ __('themes_b2c.catalog.erp_family_hierarchy') }}
                        </div>
                    </div>

                    <div class="small text-muted">
                        {{ trans_choice('themes_b2c.catalog.categories_count', $categoryRows->count(), ['count' => $categoryRows->count()]) }}
                    </div>
                </div>

                <div class="card-body">
                    @if($categoryRows->isEmpty())
                        <div class="alert alert-light border mb-0">
                            {{ __('themes_b2c.catalog.no_categories_available') }}
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach($categoryRows as $categoryRow)
                                <div class="col-12 col-md-6 col-xl-4">
                                    <a href="{{ $categoryRow['url'] }}" class="text-decoration-none text-reset">
                                        <div class="card border-0 shadow-sm h-100 category-card transition-hover">
                                            <div class="card-body d-flex flex-column">
                                                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                    <div class="fw-semibold">{{ $categoryRow['label'] }}</div>
                                                    <span class="text-muted small">
                                                        <i class="fa-solid fa-chevron-right"></i>
                                                    </span>
                                                </div>

                                                @if($categoryRow['show_description'])
                                                    <div class="text-muted small mb-3">
                                                        {{ $categoryRow['description'] }}
                                                    </div>
                                                @endif

                                                <div class="mt-auto pt-2">
                                                    <span class="btn btn-sm btn-outline-primary">
                                                        {{ __('themes_b2c.catalog.explore_category') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
