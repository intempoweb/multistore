@extends($storefrontLayout)

@section('title', $seo['title'] ?? ($category['label'] ?? __('Collezione')))

@section('content')
<div class="intempo-b2c-page intempo-b2c-shell">
    <header class="intempo-b2c-page-heading">
        <p class="intempo-b2c-eyebrow">{{ __('Collezione') }}</p>
        <h1>{{ $seo['heading'] ?? ($category['label'] ?? __('Collezione')) }}</h1>
        @if(!empty($seo['intro']))
            <p>{{ $seo['intro'] }}</p>
        @elseif(!empty($category['description']) && $category['description'] !== $category['label'])
            <p>{{ $category['description'] }}</p>
        @endif
    </header>

    @include('storefront.themes.b2c.intemposhop.partials.product-listing')
</div>
@endsection
