@include('storefront.base.partials.product-listing', [
    'listingClassPrefix' => 'intempo-b2c',
    'listingSortId' => 'intempo_b2c_listing_sort',
    'listingCategoryFallback' => __('themes_b2c.catalog.catalog'),
    'listingColumnClass' => $ciakProductColClass,
])
