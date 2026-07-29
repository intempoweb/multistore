@extends($storefrontLayout)

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $store->name))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@push('styles')
    <style>
        body.theme-ready .ready-category-nav { display: none; }
    </style>
@endpush

@section('content')
@php
    $heroImage = $heroMedia->first();
    $productTabs = $readyProductTabs ?? collect();
    $visualCollections = $readyVisualCollections ?? collect();
    $spotlightBanner = $readySpotlightBanner ?? null;
    $featuredIntro = $readyFeaturedIntro ?? null;
    $newsletterBlock = $readyNewsletter ?? null;
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
            <p class="ready-eyebrow">{{ $hero?->subtitle ?: 'Ready' }}</p>
            <h1 id="ready-home-hero-title">{{ $hero?->title ?: 'Plein Air' }}</h1>
            <p>{{ $hero?->content ?: "Vivi l'outdoor senza pensieri" }}</p>
            <a class="ready-primary-link" href="{{ filled($hero?->button_label) ? $heroButtonUrl : $catalogueUrl }}" @if($hero?->button_new_tab) target="_blank" rel="noopener" @endif>
                {{ $hero?->button_label ?: 'Scopri la collezione' }}
                <i data-lucide="arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </section>

    <section class="ready-story ready-shell" aria-labelledby="ready-story-title">
        <h2 id="ready-story-title">Accessori per la tua vita in movimento</h2>
        <p>{{ $storyContent ?: "Se sei sempre in movimento, hai bisogno di accessori che siano pronti quanto te. Ready e' il brand di accessori smart e funzionali, progettati per semplificarti la vita, senza rinunciare allo stile." }}</p>
    </section>

    <section class="ready-products" aria-labelledby="ready-featured-title" @if($productTabs->isNotEmpty()) data-ready-product-tabs @endif>
        <div class="ready-products-panel ready-shell">
            <header class="ready-products-heading">
                <h2 id="ready-featured-title">{{ $featuredIntro?->title ?: 'Be smart, be ready' }}</h2>

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
                @else
                    <div class="ready-product-pills" aria-label="Collezioni prodotto Ready">
                        <span>Tempo libero</span>
                        <span>Sport</span>
                        <span>Outdoor</span>
                    </div>
                @endif
            </header>

            @if($productTabs->isNotEmpty())
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
                            <div class="ready-product-carousel">
                                <button type="button" class="ready-product-arrow is-prev" data-ready-products-prev aria-label="Prodotti precedenti">
                                    <i data-lucide="chevron-left"></i>
                                </button>
                                <div class="ready-product-track" data-ready-product-track>
                                    @foreach($tab['rows']->take(10) as $row)
                                        <div class="ready-product-slide">
                                            @include('storefront.themes.b2c.ready.partials.home-product', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="ready-product-arrow is-next" data-ready-products-next aria-label="Prodotti successivi">
                                    <i data-lucide="chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if($visualCollections->isNotEmpty())
        <section class="ready-visual-collections" aria-label="Collezioni Ready">
            <div class="ready-visual-grid">
                @foreach($visualCollections as $collection)
                    <a class="ready-visual-card" href="{{ $collection['url'] ?? $catalogueUrl }}">
                        @if(($collection['media_type'] ?? 'image') === 'video' && filled($collection['video'] ?? null))
                            <video autoplay muted loop playsinline preload="metadata" poster="{{ $collection['image'] ?? '' }}">
                                <source src="{{ $collection['video'] }}" type="video/mp4">
                            </video>
                        @else
                            <picture>
                                @if(filled($collection['mobile_image'] ?? null))
                                    <source media="(max-width: 767px)" srcset="{{ $collection['mobile_image'] }}">
                                @endif
                                <img src="{{ $collection['image'] }}" alt="{{ $collection['title'] }}" loading="lazy" decoding="async">
                            </picture>
                        @endif
                        <span>
                            <strong>{{ $collection['title'] }}</strong>
                            <small>{{ $collection['content'] ?? 'Visualizza la collezione' }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($spotlightBanner)
        <section class="ready-spotlight" aria-labelledby="ready-spotlight-title">
            @if(($spotlightBanner['media_type'] ?? 'image') === 'video' && filled($spotlightBanner['video'] ?? null))
                <video autoplay muted loop playsinline preload="metadata" poster="{{ $spotlightBanner['image'] ?? '' }}">
                    <source src="{{ $spotlightBanner['video'] }}" type="video/mp4">
                </video>
            @else
                <picture>
                    @if(filled($spotlightBanner['mobile_image'] ?? null))
                        <source media="(max-width: 767px)" srcset="{{ $spotlightBanner['mobile_image'] }}">
                    @endif
                    <img src="{{ $spotlightBanner['image'] }}" alt="{{ $spotlightBanner['title'] }}" loading="lazy" decoding="async">
                </picture>
            @endif
            <div class="ready-spotlight-copy">
                <p class="ready-eyebrow">{{ $spotlightBanner['eyebrow'] }}</p>
                <h2 id="ready-spotlight-title">{{ $spotlightBanner['title'] }}</h2>
                <p>{{ $spotlightBanner['content'] }}</p>
                <a class="ready-primary-link" href="{{ $spotlightBanner['url'] }}">
                    {{ $spotlightBanner['button_label'] ?? 'Scopri di più' }}
                    <i data-lucide="arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </section>
    @endif

    <section class="ready-newsletter ready-shell" aria-labelledby="ready-newsletter-title">
        <h2 id="ready-newsletter-title">{{ $newsletterBlock?->title ?: 'Newsletter' }}</h2>
        <p>{{ $newsletterBlock?->content ?: 'Rimani aggiornato sulle novità, promozioni e nuovi arrivi.' }}</p>
        <form class="ready-newsletter-form" action="#" method="get">
            <label class="visually-hidden" for="ready-newsletter-email">Email</label>
            <input id="ready-newsletter-email" type="email" name="email" placeholder="Inserisci la tua email" autocomplete="email">
            <button type="submit">Iscriviti</button>
            <label class="ready-newsletter-consent">
                <input type="checkbox" name="privacy" value="1">
                <span>Accetto i Termini e condizioni</span>
            </label>
        </form>
    </section>

    @if($instagramSection && $instagramSection['items']->isNotEmpty())
        <section class="ready-social" aria-labelledby="ready-social-title">
            <div class="ready-social-copy">
                <h2 id="ready-social-title">Segui il mondo Ready</h2>
                <p>Su Instagram</p>
                @if(filled($instagramSection['button_url'] ?? null))
                    <a href="{{ $instagramSection['button_url'] }}" @if($instagramSection['block']->button_new_tab ?? false) target="_blank" rel="noopener" @endif>
                        @@readyofficial.it
                    </a>
                @endif
            </div>
            <div class="ready-social-grid" aria-label="Instagram Ready">
                @foreach($instagramSection['items']->take(6) as $item)
                    <figure class="ready-social-card {{ $item['type'] === 'video' ? 'is-video' : '' }}">
                        @if(!empty($item['permalink']))<a href="{{ $item['permalink'] }}" target="_blank" rel="noopener" aria-label="Apri il post Instagram">@endif
                            @if($item['type'] === 'video')
                                <video muted playsinline preload="metadata" poster="{{ $item['poster'] ?: $item['desktop'] }}">
                                    <source src="{{ $item['desktop'] }}" type="video/mp4">
                                </video>
                            @else
                                <img src="{{ $item['desktop'] }}" alt="{{ $item['alt'] }}" loading="lazy" decoding="async">
                            @endif
                            <figcaption><i data-lucide="instagram"></i> Instagram</figcaption>
                        @if(!empty($item['permalink']))</a>@endif
                    </figure>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
