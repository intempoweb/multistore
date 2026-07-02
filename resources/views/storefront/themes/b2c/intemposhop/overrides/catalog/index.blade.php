@extends($storefrontLayout)

@section('title', $seo['title'] ?? __('themes_b2c.catalog.catalog'))

@section('content')
<div class="intempo-b2c-page intempo-b2c-shell">
    <header class="intempo-b2c-page-heading">
        <p class="intempo-b2c-eyebrow">{{ __('themes_b2c.catalog.catalog') }}</p>
        <h1>{{ $seo['heading'] ?? __('themes_b2c.intempo.all_shop') }}</h1>
        @if(!empty($seo['intro']))<p>{{ $seo['intro'] }}</p>@endif
    </header>

    @include('storefront.themes.b2c.intemposhop.partials.product-listing')
</div>
@endsection
