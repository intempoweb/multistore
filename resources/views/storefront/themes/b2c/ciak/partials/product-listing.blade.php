@include('storefront.base.partials.product-listing', [
    'listingClassPrefix' => 'ciak',
    'listingSortId' => 'ciak_listing_sort',
    'listingCategoryFallback' => __('themes_b2c.catalog.catalog'),
    'listingColumnClass' => $ciakProductColClass,
])
