@extends($storefrontLayout)

@section('title', ($store->name ?? 'Catalogo') . ' - Catalogo')

@section('content')
@php
    $categories = collect($categories ?? []);
    $catalogIndexUrl = route('storefront.catalog.index');
@endphp

<div class="row g-4 storefront-category-page" data-storefront-category-page>

    <div class="col-12">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-4 p-lg-5">
                <div class="text-muted small text-uppercase mb-2">
                    {{ $store?->is_b2b ? 'Catalogo B2B' : 'Catalogo Store' }}
                </div>

                <h1 class="h2 fw-bold mb-3">
                    Catalogo {{ $store->name ?? 'Store' }}
                </h1>

                <p class="text-secondary mb-0 col-xl-8">
                    Esplora le categorie disponibili nello storefront corrente.
                    La navigazione, la disponibilità prodotti e i prezzi possono
                    variare in base al tipo di store e al cliente autenticato.
                </p>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-3">
        <div class="storefront-sidebar-wrapper">
            @includeIf('storefront.base.partials.sidebar', [
                'sidebarContext' => 'catalog',
                'sidebarTitle' => 'Categorie',
                'slug' => null,
                'childrenCategories' => $categories,
                'filterFacets' => collect(),
                'activeFilters' => collect(),
                'hasActiveFilters' => false,
                'sidebarActionUrl' => $catalogIndexUrl,
                'sidebarResetUrl' => $catalogIndexUrl,
                'emptyFiltersMessage' => 'Seleziona una categoria per visualizzare i filtri prodotto basati sugli attributi delle varianti.',
            ])
        </div>
    </div>

    <div class="col-12 col-lg-9">
        <div class="storefront-product-results">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2 class="h5 mb-1">Categorie catalogo</h2>
                        <div class="text-muted small">
                            Gerarchia famiglie ERP disponibile per lo store.
                        </div>
                    </div>

                    <div class="small text-muted">
                        {{ $categories->count() }} categorie
                    </div>
                </div>

                <div class="card-body">
                    @if($categories->isEmpty())
                        <div class="alert alert-light border mb-0">
                            Nessuna categoria disponibile per questo store.
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach($categories as $category)
                                @php
                                    $label = trim((string) ($category['label'] ?? 'Categoria'));
                                    $description = trim((string) ($category['description'] ?? ''));
                                    $showDescription = $description !== '' && $description !== $label;
                                    $categorySlug = $category['slug'] ?? null;
                                @endphp

                                @if($categorySlug)
                                    <div class="col-12 col-md-6 col-xl-4">
                                        <a href="{{ route('storefront.category.show', $categorySlug) }}" class="text-decoration-none text-reset">
                                            <div class="card border-0 shadow-sm h-100 category-card transition-hover">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                                        <div class="fw-semibold">{{ $label }}</div>
                                                        <span class="text-muted small">
                                                            <i class="fa-solid fa-chevron-right"></i>
                                                        </span>
                                                    </div>

                                                    @if($showDescription)
                                                        <div class="text-muted small mb-3">
                                                            {{ $description }}
                                                        </div>
                                                    @endif

                                                    <div class="mt-auto pt-2">
                                                        <span class="btn btn-sm btn-outline-primary">
                                                            Esplora categoria
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection