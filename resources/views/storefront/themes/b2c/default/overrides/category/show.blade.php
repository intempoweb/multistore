@extends($storefrontLayout)

@section('title', ($category['label'] ?? 'Categoria') . ' - ' . ($store->name ?? 'Store'))

@section('content')

<div class="row g-4">

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">

                <div class="text-muted small mb-1">
                    Categoria
                </div>

                <h1 class="h3 fw-bold mb-2">
                    {{ $category['label'] ?? $slug }}
                </h1>

                @if(!empty($category['description']) && trim((string) $category['description']) !== trim((string) ($category['label'] ?? '')))
                    <div class="text-muted small">
                        {{ $category['description'] }}
                    </div>
                @endif

            </div>
        </div>
    </div>

    @if(!empty($childrenCategories) && $childrenCategories->isNotEmpty())
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h2 class="h6 mb-1">Sottocategorie</h2>
                    <div class="text-muted small">
                        Naviga i livelli successivi della gerarchia ERP.
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        @foreach($childrenCategories as $childCategory)
                            @php
                                $childLabel = trim((string) ($childCategory['label'] ?? 'Categoria'));
                                $childDescription = trim((string) ($childCategory['description'] ?? ''));
                                $showChildDescription = $childDescription !== '' && $childDescription !== $childLabel;
                            @endphp

                            <div class="col-12 col-md-6 col-xl-4">
                                <a
                                    href="{{ route('storefront.category.show', $childCategory['slug']) }}"
                                    class="text-decoration-none text-reset"
                                >
                                    <div class="border rounded-3 p-3 h-100 bg-white d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-semibold mb-1">
                                                {{ $childLabel }}
                                            </div>

                                            @if($showChildDescription)
                                                <div class="text-muted small">
                                                    {{ $childDescription }}
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
                </div>
            </div>
        </div>
    @endif

    <div class="col-12 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h2 class="h6 mb-0">Filtri</h2>
            </div>

            <div class="card-body">
                <div class="text-muted small">
                    Filtri prodotto collegati agli attributi ERP come colore, formato e caratteristiche tecniche.
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-9">
        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <div class="fw-semibold">
                    Prodotti categoria
                </div>

                <div class="small text-muted">
                    {{ $products->total() ?? 0 }} prodotti
                </div>
            </div>

            <div class="card-body">

                @if($products->isEmpty())
                    <div class="alert alert-light border mb-0">
                        Nessun prodotto disponibile in questa categoria.
                    </div>
                @else
                    <div class="row g-3">

                        @foreach($products as $product)
                            @php
                                $name = $product->display_name ?? $product->sku;
                                $image = $product->main_image_url;
                                $price = $product->effective_price;
                                $variants = $product->listing_variant_count ?? 1;
                                $targetSku = $product->listing_target_sku ?? $product->sku;

                                $variantOptions = collect($product->listing_variant_options ?? []);

                                $colorOptions = $variantOptions
                                    ->filter(fn ($item) => !empty($item['color']['value']))
                                    ->unique(fn ($item) => $item['color']['value'])
                                    ->values();

                                $formatOptions = $variantOptions
                                    ->filter(fn ($item) => !empty($item['format']['value']))
                                    ->unique(fn ($item) => $item['format']['value'])
                                    ->values();
                            @endphp

                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="card h-100 border-0 shadow-sm">

                                    @if($image)
                                        <img
                                            src="{{ $image }}"
                                            class="card-img-top"
                                            style="object-fit:cover;height:220px"
                                            alt="{{ $name }}"
                                        >
                                    @else
                                        <div
                                            class="d-flex align-items-center justify-content-center bg-light text-muted small"
                                            style="height:220px"
                                        >
                                            Nessuna immagine
                                        </div>
                                    @endif

                                    <div class="card-body d-flex flex-column">

                                        <div class="small text-muted mb-1">
                                            SKU {{ $targetSku }}
                                        </div>

                                        <div class="fw-semibold mb-2">
                                            {{ $name }}
                                        </div>

                                        @if($variants > 1)
                                            <div class="small text-muted mb-2">
                                                {{ $variants }} varianti disponibili
                                            </div>
                                        @endif

                                        @if($colorOptions->isNotEmpty())
                                            <div class="mb-2">
                                                <div class="small text-muted mb-1">Colore</div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @foreach($colorOptions as $option)
                                                        <a
                                                            href="{{ route('storefront.product.show', $option['sku']) }}"
                                                            class="text-decoration-none"
                                                            title="{{ $option['color']['value'] ?? '' }}"
                                                        >
                                                            @if(!empty($option['color']['swatch_url']))
                                                                <span
                                                                    class="d-inline-flex border rounded-circle overflow-hidden"
                                                                    style="width: 24px; height: 24px;"
                                                                >
                                                                    <img
                                                                        src="{{ $option['color']['swatch_url'] }}"
                                                                        alt="{{ $option['color']['value'] ?? '' }}"
                                                                        style="width:100%; height:100%; object-fit:cover;"
                                                                    >
                                                                </span>
                                                            @else
                                                                <span class="badge text-bg-light border">
                                                                    {{ $option['color']['value'] ?? '-' }}
                                                                </span>
                                                            @endif
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @if($formatOptions->isNotEmpty())
                                            <div class="mb-2">
                                                <div class="small text-muted mb-1">Formato</div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @foreach($formatOptions as $option)
                                                        <a
                                                            href="{{ route('storefront.product.show', $option['sku']) }}"
                                                            class="badge rounded-pill text-bg-light border text-decoration-none"
                                                        >
                                                            {{ $option['format']['value'] ?? '-' }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div class="fw-semibold mb-3 mt-2">
                                            @if($price !== null)
                                                € {{ number_format((float) $price, 2, ',', '.') }}
                                            @else
                                                —
                                            @endif
                                        </div>

                                        <div class="mt-auto">
                                            <a
                                                href="{{ route('storefront.product.show', $targetSku) }}"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                Vedi prodotto
                                            </a>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        @endforeach

                    </div>

                    <div class="mt-4">
                        {{ $products->links() }}
                    </div>
                @endif

            </div>

        </div>
    </div>

</div>

@endsection