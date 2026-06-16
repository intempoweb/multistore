@extends($storefrontLayout)

@section('title', 'Ricerca' . ($query ? ' "' . $query . '"' : '') . ' - ' . ($store->name ?? 'Store'))

@section('content')
@php
    $query = trim((string) ($query ?? request('q', '')));
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];

    $currentSort = $currentSort ?? request('sort', 'default');
    $grid = (int) request('grid', 4);
    $grid = in_array($grid, [2, 3, 4], true) ? $grid : 4;

    $productColClass = match ($grid) {
        2 => 'col-12 col-md-6',
        3 => 'col-12 col-md-6 col-xl-4',
        default => 'col-12 col-sm-6 col-lg-4 col-xl-3',
    };

    $baseQuery = request()->except(['page', 'grid', 'sort']);

    if ($agentContextId !== '') {
        $baseQuery['agent_context'] = $agentContextId;
    }

    $searchActionUrl = route('storefront.search.index', $contextParams);
    $total = $products->total() ?? 0;
@endphp

<div class="row g-4 storefront-search-page">

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="text-muted small mb-1">Ricerca prodotti</div>

                <h1 class="h3 fw-bold mb-2">
                    @if($query !== '')
                        Risultati per “{{ $query }}”
                    @else
                        Cerca nel catalogo
                    @endif
                </h1>

                <div class="text-muted small">
                    {{ $total }} prodotti trovati
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="fw-semibold">Prodotti</div>
                    <div class="small text-muted">
                        Ricerca su nome, SKU, barcode, marca e varianti.
                    </div>
                </div>

                <form method="GET" action="{{ $searchActionUrl }}" class="d-flex align-items-center gap-3 flex-wrap">
                    @foreach($baseQuery as $key => $value)
                        @if(is_array($value))
                            @foreach($value as $item)
                                <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach

                    <div class="btn-group btn-group-sm" role="group" aria-label="Prodotti per riga">
                        @foreach([2, 3, 4] as $gridOption)
                            <button
                                type="submit"
                                name="grid"
                                value="{{ $gridOption }}"
                                class="btn {{ $grid === $gridOption ? 'btn-dark' : 'btn-outline-secondary' }}"
                            >
                                <i class="fa-solid fa-grip me-1"></i>
                                {{ $gridOption }}
                            </button>
                        @endforeach
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <label for="search_products_sort" class="small text-muted fw-semibold mb-0">
                            Ordina
                        </label>

                        <select
                            name="sort"
                            id="search_products_sort"
                            class="form-select form-select-sm border-0 bg-light shadow-none rounded-pill px-3"
                            onchange="this.form.submit()"
                            style="min-width: 170px;"
                        >
                            <option value="default" @selected($currentSort === 'default')>Predefinito</option>
                            <option value="newest" @selected($currentSort === 'newest')>Novità</option>
                            <option value="name_asc" @selected($currentSort === 'name_asc')>Nome A-Z</option>
                            <option value="name_desc" @selected($currentSort === 'name_desc')>Nome Z-A</option>
                            <option value="sku_asc" @selected($currentSort === 'sku_asc')>SKU crescente</option>
                            <option value="sku_desc" @selected($currentSort === 'sku_desc')>SKU decrescente</option>
                            <option value="price_asc" @selected($currentSort === 'price_asc')>Prezzo crescente</option>
                            <option value="price_desc" @selected($currentSort === 'price_desc')>Prezzo decrescente</option>
                        </select>
                    </div>
                </form>
            </div>

            <div class="card-body">
                @if($query === '')
                    <div class="alert alert-light border mb-0">
                        Inserisci almeno 2 caratteri nella barra di ricerca.
                    </div>
                @elseif($products->isEmpty())
                    <div class="alert alert-light border mb-0">
                        Nessun prodotto trovato per “{{ $query }}”.
                    </div>
                @else
                    <div class="row g-3">
                        @foreach($products as $product)
                            <div class="{{ $productColClass }}">
                                @include('storefront.base.partials.product-card', [
                                    'product' => $product,
                                    'listingCard' => collect(),
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
            </div>
        </div>
    </div>

</div>
@endsection