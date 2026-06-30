@extends($storefrontLayout)

@section('title', $storefrontPage?->meta_title ?: ($storefrontPage?->title ?: $store->name))
@section('meta_description', $storefrontPage?->meta_description ?: $storefrontPage?->description)

@section('content')
@php
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];
    $homeCategories = collect($rootCategories ?? [])->filter(fn ($category) => filled($category['label'] ?? null) && filled($category['slug'] ?? null))->values();
    $findCategoryUrl = static function (array $terms) use ($homeCategories, $contextParams) {
        $category = $homeCategories->first(function ($category) use ($terms) {
            $haystack = mb_strtolower(trim((string) (($category['label'] ?? '').' '.($category['slug'] ?? '').' '.($category['description'] ?? ''))));

            return collect($terms)->contains(fn ($term) => str_contains($haystack, $term));
        });

        return $category && filled($category['slug'] ?? null)
            ? route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams))
            : route('storefront.catalog.index', $contextParams);
    };
    $storyTitle = $aboutSection['block']->title ?? __('Chi siamo');
    $storyContent = $aboutSection['block']->content ?? __('Intempo crea, produce e distribuisce prodotti pensati per organizzare il tempo, accompagnare il lavoro e portare funzionalità negli spazi quotidiani.');
    $catalogueUrl = route('storefront.catalog.index', $contextParams);
    $locatorUrl = route('storefront.store-locator.index', $contextParams);
    $b2bUrl = 'https://new.intempodistribution.it/it/login';
    $intempoAreas = collect([
        [
            'label' => __('Agende'),
            'title' => __('Da sempre creiamo agende'),
            'content' => __('Il cuore storico di Intempo: agende e planner per organizzare giornate, lavoro e progetti con praticità e cura.'),
            'icon' => asset('images/themes/b2c/intempo/icons/intempo-diaries-icons.png'),
            'url' => $findCategoryUrl(['diar', 'agenda', 'agende']),
        ],
        [
            'label' => __('Lifestyle'),
            'title' => __('Lifestyle'),
            'content' => __('Accessori, pelletteria e oggetti quotidiani scelti per accompagnare ogni giorno con stile sobrio e funzionale.'),
            'icon' => asset('images/themes/b2c/intempo/icons/intempo-pelletteria-icons.png'),
            'url' => $findCategoryUrl(['lifestyle', 'pelletter', 'accessor']),
        ],
        [
            'label' => __('Casa e ufficio'),
            'title' => __('Design per casa e ufficio'),
            'content' => __('Soluzioni per scrivania, home office e ambienti di lavoro: essenziali, ordinate, facili da vivere.'),
            'icon' => asset('images/themes/b2c/intempo/icons/intempo-home-office-icons.png'),
            'url' => $findCategoryUrl(['home', 'office', 'ufficio', 'arredo', 'casa']),
        ],
    ]);
@endphp

<div class="intempo-b2c-home">
    <section class="intempo-b2c-hero container-fluid p-0" data-intempo-home-hero>
        <div class="intempo-b2c-hero-copy">
            <div class="intempo-b2c-hero-copy-inner">
                <p class="intempo-b2c-eyebrow">{{ $hero?->subtitle ?: __('Intempo') }}</p>
                <h1>{{ $hero?->title ?: __('Da sempre creiamo agende') }}</h1>
                <p>{{ $hero?->content ?: __('Agende, lifestyle, casa e ufficio: prodotti pensati per organizzare il tempo e rendere più semplici gli spazi di ogni giorno.') }}</p>
                <div class="intempo-b2c-hero-actions">
                    <a class="intempo-b2c-primary-link" href="{{ filled($hero?->button_label) ? $heroButtonUrl : $catalogueUrl }}" @if($hero?->button_new_tab) target="_blank" rel="noopener" @endif>
                        {{ $hero?->button_label ?: __('Scopri la collezione') }}
                        <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </a>
                    <a class="intempo-b2c-secondary-link" href="{{ $locatorUrl }}">{{ __('Trova un punto vendita') }}</a>
                </div>
            </div>
        </div>
        <div class="intempo-b2c-hero-media {{ $heroMedia->isEmpty() ? 'is-empty' : '' }}">
            @foreach($heroMedia as $index => $media)
                <div class="intempo-b2c-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-intempo-hero-slide>
                    @if($media['type'] === 'video')
                        <video muted loop playsinline preload="metadata" poster="{{ $media['poster'] }}"><source src="{{ $media['desktop'] }}"></video>
                    @else
                        <picture>
                            @if($media['mobile'])<source media="(max-width: 767px)" srcset="{{ $media['mobile'] }}">@endif
                            <img src="{{ $media['desktop'] }}" alt="{{ $media['alt'] ?: ($hero?->title ?: $store->name) }}" fetchpriority="high">
                        </picture>
                    @endif
                </div>
            @endforeach

            @if($heroMedia->isEmpty())
                <div class="intempo-b2c-hero-placeholder">
                    <span>INTEMPO</span>
                    <small>{{ __('Agende · Lifestyle · Casa e ufficio') }}</small>
                </div>
            @endif

            @if($heroMedia->count() > 1)
                <div class="intempo-b2c-hero-controls">
                    <button type="button" data-intempo-hero-prev aria-label="{{ __('Precedente') }}"><i data-lucide="arrow-left"></i></button>
                    <span><b data-intempo-hero-current>1</b> / {{ $heroMedia->count() }}</span>
                    <button type="button" data-intempo-hero-next aria-label="{{ __('Successivo') }}"><i data-lucide="arrow-right"></i></button>
                </div>
            @endif
        </div>
    </section>

    <section class="intempo-b2c-category-section intempo-b2c-shell" aria-labelledby="intempo-b2c-categories-title">
        <header class="intempo-b2c-section-heading">
            <div>
                <p class="intempo-b2c-eyebrow">{{ __('Collezioni') }}</p>
                <h2 id="intempo-b2c-categories-title">{{ __('Agende, lifestyle, casa e ufficio') }}</h2>
            </div>
            <a href="{{ $catalogueUrl }}">{{ __('Tutto il catalogo') }}<i data-lucide="arrow-right"></i></a>
        </header>
        <div class="intempo-b2c-category-grid is-featured-areas">
            @foreach($intempoAreas as $area)
                <a class="intempo-b2c-category-card is-featured-area" href="{{ $area['url'] }}">
                    <span><img src="{{ $area['icon'] }}" alt="" loading="lazy" decoding="async"></span>
                    <small>{{ $area['label'] }}</small>
                    <strong>{{ $area['title'] }}</strong>
                    <em>{{ $area['content'] }}</em>
                </a>
            @endforeach
        </div>
    </section>

    <section class="intempo-b2c-story intempo-b2c-shell" aria-label="{{ __('Chi siamo') }}">
        <div class="intempo-b2c-story-copy">
            <p class="intempo-b2c-eyebrow">{{ $aboutSection['block']->subtitle ?? __('Chi siamo') }}</p>
            <h2>{{ $storyTitle }}</h2>
            <p>{{ $storyContent }}</p>
            <a class="intempo-b2c-text-link" href="{{ $aboutSection['button_url'] ?? $catalogueUrl }}">
                {{ $aboutSection['block']->button_label ?? __('Esplora il mondo Intempo') }}
                <i data-lucide="arrow-right"></i>
            </a>
        </div>
        <div class="intempo-b2c-story-panels">
            <article>
                <span>{{ __('01') }}</span>
                <h3>{{ __('Da sempre creiamo agende') }}</h3>
                <p>{{ __('Un prodotto storico, pensato per dare forma a impegni, idee e progetti.') }}</p>
            </article>
            <article>
                <span>{{ __('02') }}</span>
                <h3>{{ __('Lifestyle') }}</h3>
                <p>{{ __('Oggetti e accessori per la vita quotidiana, con uno stile essenziale e riconoscibile.') }}</p>
            </article>
            <article>
                <span>{{ __('03') }}</span>
                <h3>{{ __('Design per casa e ufficio') }}</h3>
                <p>{{ __('Soluzioni funzionali per spazi di lavoro, studio e home office.') }}</p>
            </article>
        </div>
    </section>

    @if($featuredRows->isNotEmpty())
        <section class="intempo-b2c-products-section intempo-b2c-shell" aria-labelledby="intempo-b2c-featured-title">
            <header class="intempo-b2c-section-heading">
                <div>
                    <p class="intempo-b2c-eyebrow">{{ __('In evidenza') }}</p>
                    <h2 id="intempo-b2c-featured-title">{{ __('Scelti per te') }}</h2>
                </div>
                <a href="{{ $catalogueUrl }}">{{ __('Vedi tutto') }}<i data-lucide="arrow-right"></i></a>
            </header>
            <div class="intempo-b2c-products-grid">
                @foreach($featuredRows as $row)
                    @include('storefront.base.partials.product-card', ['product' => $row['product'], 'listingCard' => $row['listingCard']])
                @endforeach
            </div>
        </section>
    @endif

    @if($instagramSection)
        <section class="intempo-b2c-instagram-section intempo-b2c-shell" aria-label="{{ $instagramSection['block']->title ?: __('Instagram Intempo') }}">
            <header class="intempo-b2c-section-heading">
                <div>
                    <p class="intempo-b2c-eyebrow">{{ $instagramSection['block']->subtitle ?: __('Social') }}</p>
                    <h2>{{ $instagramSection['block']->title ?: __('Intempo su Instagram') }}</h2>
                    @if($instagramSection['block']->content)<p>{{ $instagramSection['block']->content }}</p>@endif
                </div>
                @if($instagramSection['block']->button_label)<a href="{{ $instagramSection['button_url'] }}" @if($instagramSection['block']->button_new_tab) target="_blank" rel="noopener" @endif>{{ $instagramSection['block']->button_label }}<i data-lucide="arrow-right"></i></a>@endif
            </header>

            @if($instagramSection['items']->isNotEmpty())
                <div class="intempo-b2c-instagram-grid">
                    @foreach($instagramSection['items']->take(12) as $item)
                        <figure class="intempo-b2c-instagram-card">
                            @if(!empty($item['permalink']))<a href="{{ $item['permalink'] }}" target="_blank" rel="noopener" aria-label="{{ __('Apri post Instagram') }}">@endif
                            @if($item['type'] === 'video')
                                <video autoplay muted loop playsinline preload="metadata" poster="{{ $item['poster'] }}"><source src="{{ $item['desktop'] }}"></video>
                            @else
                                <picture>
                                    @if($item['mobile'])<source media="(max-width:767px)" srcset="{{ $item['mobile'] }}">@endif
                                    <img src="{{ $item['desktop'] }}" alt="{{ $item['alt'] }}" loading="lazy" decoding="async">
                                </picture>
                            @endif
                            <figcaption><i data-lucide="instagram"></i><span>{{ __('Instagram') }}</span></figcaption>
                            @if(!empty($item['permalink']))</a>@endif
                        </figure>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
