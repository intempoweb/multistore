@extends($storefrontLayout)

@section('title', ($store->name ?? 'Catalogo') . ' - Catalogo')

@section('content')
<div class="row g-4">

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">

                <div class="text-muted small text-uppercase mb-2">Catalogo B2C</div>

                <h1 class="h2 fw-bold mb-3">
                    Catalogo {{ $store->name ?? 'Store' }}
                </h1>

                <p class="text-secondary mb-4">
                    Categorie disponibili per lo store corrente. Le categorie derivano
                    direttamente dalla gerarchia ERP filtrata per ditta e site del contesto storefront.
                </p>

                <div class="row g-3">

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <div class="text-muted small mb-1">Store</div>
                            <div class="fw-semibold">{{ $store->name ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <div class="text-muted small mb-1">Dominio</div>
                            <div class="fw-semibold">{{ $store->domain ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <div class="text-muted small mb-1">Ditta / Site</div>
                            <div class="fw-semibold">
                                {{ $store->ditta_cg18 ?? '-' }} / {{ $store->erp_site_code ?? '-' }}
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-3 p-3 h-100 bg-light">
                            <div class="text-muted small mb-1">Tipologia</div>
                            <div class="fw-semibold">B2C</div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm h-100">

            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
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
                            @endphp

                            <div class="col-12 col-md-6">
                                <a
                                    href="{{ route('storefront.category.show', $category['slug']) }}"
                                    class="text-decoration-none text-reset"
                                >
                                    <div class="border rounded-3 p-3 h-100 bg-white d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold mb-1">
                                                {{ $label }}
                                            </div>

                                            @if($showDescription)
                                                <div class="text-muted small">
                                                    {{ $description }}
                                                </div>
                                            @endif
                                        </div>

                                        <span class="text-muted">
                                            <i class="fa-solid fa-chevron-right"></i>
                                        </span>
                                    </div>
                                </a>
                            </div>

                        @endforeach

                    </div>

                @endif

            </div>

        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm h-100">

            <div class="card-header bg-white border-0">
                <h2 class="h5 mb-1">Azioni rapide</h2>
                <div class="text-muted small">Navigazione storefront.</div>
            </div>

            <div class="card-body d-grid gap-2">

                <a href="{{ route('storefront.home') }}" class="btn btn-outline-dark">
                    <i class="fa-solid fa-house me-2"></i>
                    Torna alla home
                </a>

                @if($categories->isNotEmpty())
                    <a href="{{ route('storefront.category.show', $categories->first()['slug']) }}" class="btn btn-outline-primary">
                        <i class="fa-solid fa-layer-group me-2"></i>
                        Apri prima categoria
                    </a>
                @endif

            </div>

        </div>
    </div>

</div>
@endsection