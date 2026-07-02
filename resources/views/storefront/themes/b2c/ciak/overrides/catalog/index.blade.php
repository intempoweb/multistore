@extends($storefrontLayout)
@section('title', $seo['title'] ?? __('themes_b2c.ciak.catalog'))
@section('content')
<div class="ciak-page ciak-shell">
    <header class="ciak-page-heading"><p class="ciak-eyebrow">{{ __('themes_b2c.ciak.catalog') }}</p><h1>{{ $seo['heading'] ?? __('themes_b2c.ciak.all_shop') }}</h1>@if(!empty($seo['intro']))<p>{{ $seo['intro'] }}</p>@endif</header>
    @include('storefront.themes.b2c.ciak.partials.product-listing')
</div>
@endsection
