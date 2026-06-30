@extends($storefrontLayout)

@section('title', $seo['title'] ?? __('Catalogo'))

@section('content')
<div class="intempo-b2c-page intempo-b2c-shell">
    <header class="intempo-b2c-page-heading">
        <p class="intempo-b2c-eyebrow">{{ __('Catalogo') }}</p>
        <h1>{{ $seo['heading'] ?? __('Tutto lo shop') }}</h1>
        @if(!empty($seo['intro']))<p>{{ $seo['intro'] }}</p>@endif
    </header>

    @include('storefront.themes.b2c.intemposhop.partials.product-listing')
</div>
@endsection
