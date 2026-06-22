@extends($storefrontLayout)
@section('title', $seo['title'] ?? __('Catalogo'))
@section('content')
<div class="ciak-page ciak-shell">
    <header class="ciak-page-heading"><p class="ciak-eyebrow">{{ __('Catalogo') }}</p><h1>{{ $seo['heading'] ?? __('Tutto lo shop') }}</h1>@if(!empty($seo['intro']))<p>{{ $seo['intro'] }}</p>@endif</header>
    @include('storefront.themes.b2c.ciak.partials.product-listing', ['sidebarContext' => 'catalog', 'sidebarActionUrl' => route('storefront.catalog.index'), 'sidebarResetUrl' => route('storefront.catalog.index'), 'slug' => null, 'childrenCategories' => $categories ?? collect()])
</div>
@endsection
