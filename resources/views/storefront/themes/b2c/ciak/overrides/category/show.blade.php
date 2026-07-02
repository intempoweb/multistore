@extends($storefrontLayout)
@section('title', $seo['title'] ?? ($category['label'] ?? __('themes_b2c.ciak.collection')))
@section('content')
<div class="ciak-page ciak-shell">
    <header class="ciak-page-heading"><p class="ciak-eyebrow">{{ __('themes_b2c.ciak.collection') }}</p><h1>{{ $seo['heading'] ?? ($category['label'] ?? __('themes_b2c.ciak.collection')) }}</h1>@if(!empty($seo['intro']))<p>{{ $seo['intro'] }}</p>@elseif(!empty($category['description']) && $category['description'] !== $category['label'])<p>{{ $category['description'] }}</p>@endif</header>
    @include('storefront.themes.b2c.ciak.partials.product-listing')
</div>
@endsection
