@extends($storefrontLayout)

@section('title', ($store->name ?? 'CIAK') . ' - ' . __('Shop'))

@section('content')
    @include('storefront.themes.b2c.ciak.partials.product-listing', [
        'listingContext' => 'catalog',
        'listingEyebrow' => __('Shop online'),
        'listingTitle' => __('Tutto CIAK'),
        'listingDescription' => __('Agende, taccuini e accessori pensati per accompagnare ogni giorno.'),
        'listingResultsTitle' => __('Tutti i prodotti'),
        'listingCategories' => $categories,
        'listingActionUrl' => route('storefront.catalog.index'),
        'listingResetUrl' => route('storefront.catalog.index'),
    ])
@endsection
