@extends($storefrontLayout)

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $store->name))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@section('content')
@php
    $heroImage = $heroMedia->first();
    $collections = $intempoAreas ?? collect();
@endphp

<div class="ready-home">
    <section class="ready-hero" aria-labelledby="ready-home-hero-title">
        @if($heroImage)
            <picture class="ready-hero-picture">
                @if(!empty($heroImage['mobile']))
                    <source media="(max-width: 767px)" srcset="{{ $heroImage['mobile'] }}">
                @endif
                <img
                    src="{{ $heroImage['desktop'] }}"
                    alt="{{ $heroImage['alt'] ?: ($hero?->title ?: $store->name) }}"
                    fetchpriority="high"
                >
            </picture>
        @endif

        <div class="ready-hero-copy">
            <p class="ready-eyebrow">{{ $hero?->subtitle ?: 'Plein Air' }}</p>
            <h1 id="ready-home-hero-title">{{ $hero?->title ?: 'Plein Air' }}</h1>
            <p>{{ $hero?->content ?: "Vivi l'outdoor senza pensieri" }}</p>
            <a class="ready-primary-link" href="{{ filled($hero?->button_label) ? $heroButtonUrl : $catalogueUrl }}" @if($hero?->button_new_tab) target="_blank" rel="noopener" @endif>
                {{ $hero?->button_label ?: 'Acquista ora' }}
                <i data-lucide="arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <section class="ready-intro ready-shell" aria-labelledby="ready-intro-title">
        <div>
            <p class="ready-eyebrow">{{ $aboutSection['block']->subtitle ?? 'Be smart, be ready' }}</p>
            <h2 id="ready-intro-title">{{ $storyTitle ?: 'Accessori per la tua vita in movimento' }}</h2>
        </div>
        <p>{{ $storyContent ?: 'Ready crea soluzioni pratiche e leggere per vivere outdoor, viaggio e tempo libero con semplicità.' }}</p>
    </section>

    @if($featuredRows->isNotEmpty())
        <section class="ready-products ready-shell" aria-labelledby="ready-featured-title">
            <header class="ready-section-heading is-row">
                <div>
                    <p class="ready-eyebrow">Be smart, be ready</p>
                    <h2 id="ready-featured-title">Sport, outdoor e tempo libero</h2>
                </div>
                <a href="{{ $catalogueUrl }}">Acquista ora<i data-lucide="arrow-right"></i></a>
            </header>

            <div class="intempo-b2c-products-grid ready-products-grid">
                @foreach($featuredRows as $row)
                    @include('storefront.base.partials.product-card', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                @endforeach
            </div>
        </section>
    @endif

    @if($collections->isNotEmpty())
        <section class="ready-collections ready-shell" aria-labelledby="ready-collections-title">
            <header class="ready-section-heading">
                <p class="ready-eyebrow">Collezioni</p>
                <h2 id="ready-collections-title">Scegli prodotti pensati per muoverti con semplicità.</h2>
            </header>

            <div class="ready-collection-grid">
                @foreach($collections as $collection)
                    <a class="ready-collection-card" href="{{ $collection['url'] ?? $catalogueUrl }}">
                        <small>{{ $collection['label'] ?? 'Collezione' }}</small>
                        <strong>{{ $collection['title'] }}</strong>
                        <span>{{ $collection['content'] }}</span>
                        <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
