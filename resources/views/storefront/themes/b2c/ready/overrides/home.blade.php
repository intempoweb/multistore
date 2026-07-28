@extends($storefrontLayout)

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $store->name))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@section('content')
@php
    $heroImage = $heroMedia->first();
    $collections = $intempoAreas ?? collect();
    $productTabs = $readyProductTabs ?? collect();
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

    <section class="ready-products" aria-labelledby="ready-featured-title" @if($productTabs->isNotEmpty()) data-ready-product-tabs @endif>
        <div class="ready-products-panel ready-shell">
            <header class="ready-section-heading is-row">
                <div>
                    <p class="ready-eyebrow">Be smart, be ready</p>
                    <h2 id="ready-featured-title">Sport, outdoor e tempo libero</h2>
                </div>
                <a href="{{ $catalogueUrl }}">Acquista ora<i data-lucide="arrow-right"></i></a>
            </header>

            @if($productTabs->isNotEmpty())
                <div class="ready-product-pills" role="tablist" aria-label="Collezioni prodotto Ready">
                    @foreach($productTabs as $index => $tab)
                        <button
                            type="button"
                            class="ready-product-pill {{ $index === 0 ? 'is-active' : '' }}"
                            role="tab"
                            id="ready-tab-{{ $tab['key'] }}"
                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                            aria-controls="ready-panel-{{ $tab['key'] }}"
                            data-ready-tab="{{ $tab['key'] }}"
                        >
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>

                <div class="ready-product-galleries">
                    @foreach($productTabs as $index => $tab)
                        <div
                            class="ready-product-gallery {{ $index === 0 ? 'is-active' : '' }}"
                            role="tabpanel"
                            id="ready-panel-{{ $tab['key'] }}"
                            aria-labelledby="ready-tab-{{ $tab['key'] }}"
                            data-ready-panel="{{ $tab['key'] }}"
                            @if($index !== 0) hidden @endif
                        >
                            <div class="ready-product-track" data-ready-product-track>
                                @foreach($tab['rows'] as $row)
                                    <div class="ready-product-slide">
                                        @include('storefront.base.partials.product-card', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="ready-product-empty" aria-hidden="true"></div>
            @endif
        </div>
    </section>

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

    @if($instagramSection)
        <section class="ready-instagram" aria-labelledby="ready-instagram-title">
            <div class="ready-shell">
                <header class="ready-section-heading is-row">
                    <div>
                        <p class="ready-eyebrow">{{ $instagramSection['block']->subtitle }}</p>
                        <h2 id="ready-instagram-title">{{ $instagramSection['block']->title }}</h2>
                        <p>{{ $instagramSection['block']->content }}</p>
                    </div>
                    @if(filled($instagramSection['button_url'] ?? null))
                        <a href="{{ $instagramSection['button_url'] }}" @if($instagramSection['block']->button_new_tab ?? false) target="_blank" rel="noopener" @endif>
                            {{ $instagramSection['block']->button_label }}
                            <i data-lucide="arrow-right"></i>
                        </a>
                    @endif
                </header>

                @if($instagramSection['items']->isNotEmpty())
                    <div class="ready-instagram-grid" aria-label="Instagram Ready">
                        @foreach($instagramSection['items']->take(8) as $item)
                            <figure class="ready-instagram-card {{ $item['type'] === 'video' ? 'is-video' : '' }}">
                                @if(!empty($item['permalink']))<a href="{{ $item['permalink'] }}" target="_blank" rel="noopener" aria-label="Apri il post Instagram">@endif
                                    @if($item['type'] === 'video')
                                        <video muted playsinline preload="metadata" poster="{{ $item['poster'] ?: $item['desktop'] }}">
                                            <source src="{{ $item['desktop'] }}" type="video/mp4">
                                        </video>
                                    @else
                                        <picture>
                                            @if(!empty($item['mobile']))
                                                <source media="(max-width: 767px)" srcset="{{ $item['mobile'] }}">
                                            @endif
                                            <img src="{{ $item['desktop'] }}" alt="{{ $item['alt'] }}" loading="lazy" decoding="async">
                                        </picture>
                                    @endif
                                    <figcaption><i data-lucide="instagram"></i> Instagram</figcaption>
                                @if(!empty($item['permalink']))</a>@endif
                            </figure>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>
@endsection
