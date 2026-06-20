@extends($storefrontLayout)

@section('title', ($category['label'] ?? __('Categoria')) . ' - ' . ($store->name ?? 'CIAK'))

@section('content')
    @include('storefront.themes.b2c.ciak.partials.product-listing', [
        'listingContext' => 'category',
        'listingEyebrow' => __('Collezione CIAK'),
        'listingTitle' => $category['label'] ?? $slug,
        'listingDescription' => !empty($category['description']) && trim((string) $category['description']) !== trim((string) ($category['label'] ?? ''))
            ? $category['description']
            : __('Scopri formati, colori e dettagli della collezione.'),
        'listingResultsTitle' => __('La collezione'),
        'listingCategories' => $childrenCategories,
        'listingActionUrl' => route('storefront.category.show', ['slug' => $slug]),
        'listingResetUrl' => route('storefront.category.show', ['slug' => $slug]),
        'listingEmptyMessage' => __('Nessun prodotto disponibile in questa categoria.'),
    ])
@endsection
