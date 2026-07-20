@extends($storefrontLayout)

@php
    $publicStoreName = trim((string) preg_replace('/\bB2[BC]\b\s*/i', '', (string) ($store?->name ?? 'TEKNIKO'))) ?: 'TEKNIKO';
    $blocksByName = collect($storefrontPageBlocks ?? [])->keyBy(fn ($block) => (string) $block->name);
    $hero = $blocksByName->get('home_hero');
    $heroTitle = trim((string) ($hero?->title ?? '')) ?: $publicStoreName;
    $heroSubtitle = trim((string) ($hero?->subtitle ?? '')) ?: $publicStoreName;
    $heroContent = trim((string) ($hero?->content ?? '')) ?: 'Zaini, accessori e soluzioni pratiche per lavoro, studio e viaggio.';
    $heroButtonLabel = trim((string) ($hero?->button_label ?? '')) ?: __('themes_b2c.ciak.discover_collection');
    $heroButtonUrl = filled($hero?->button_url)
        ? (str_starts_with((string) $hero->button_url, '/') ? url($hero->button_url) : $hero->button_url)
        : route('storefront.catalog.index', $contextParams ?? []);
    $featuredProducts = collect(method_exists($products, 'items') ? $products->items() : ($products ?? []))->take(8);
@endphp

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $publicStoreName))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@section('content')
<div class="ciak-home teknikoshop-home">
    <section class="ciak-hero container-fluid p-0">
        <div class="ciak-hero-copy">
            <div class="ciak-hero-copy-inner">
                <p class="ciak-eyebrow">{{ $heroSubtitle }}</p>
                <h1>{{ $heroTitle }}</h1>
                <p class="ciak-lead">{{ $heroContent }}</p>
                <a class="ciak-primary-link" href="{{ $heroButtonUrl }}" @if($hero?->button_new_tab) target="_blank" rel="noopener" @endif>
                    {{ $heroButtonLabel }}
                    <i data-lucide="arrow-right"></i>
                </a>
            </div>
        </div>
        <div class="ciak-hero-media is-empty" aria-hidden="true"></div>
    </section>

    @if($rootCategories->isNotEmpty())
        <section class="ciak-section ciak-shell">
            <header class="ciak-section-heading">
                <p class="ciak-eyebrow">{{ __('themes_b2c.catalog.categories') }}</p>
                <h2>Esplora {{ $publicStoreName }}</h2>
            </header>
            <div class="ciak-category-grid">
                @foreach($rootCategories->take(6) as $category)
                    <a class="ciak-category-card" href="{{ route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams ?? [])) }}">
                        <span>{{ $category['label'] }}</span>
                        <i data-lucide="arrow-up-right"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($featuredProducts->isNotEmpty())
        <section class="ciak-featured-section ciak-shell">
            <header class="ciak-section-heading">
                <p class="ciak-eyebrow">In evidenza</p>
                <h2>Prodotti in evidenza</h2>
                <a href="{{ route('storefront.catalog.index', $contextParams ?? []) }}">{{ __('themes_b2c.ciak.view_all') }} <i data-lucide="arrow-right"></i></a>
            </header>
            <div class="ciak-products-grid">
                @foreach($featuredProducts as $product)
                    @include('storefront.base.partials.product-card', [
                        'product' => $product,
                        'listingCard' => collect($listingCardsByProductSku->get((string) $product->sku, [])),
                        'store' => $store,
                    ])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
