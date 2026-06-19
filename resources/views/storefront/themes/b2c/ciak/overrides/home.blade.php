{{-- resources/views/storefront/themes/b2c/ciak/overrides/home.blade.php --}}
@extends($storefrontLayout)

@php
    use App\Models\ProductCardViewModel;
    use App\Models\StorefrontPage;
    use App\Repositories\Storefront\CatalogRepository;

    $locale = $locale ?? app()->getLocale();
    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];

    $page = $store
        ? StorefrontPage::query()
            ->where('store_id', $store->id)
            ->where('slug', 'home')
            ->where('is_active', true)
            ->with('activeBlocks')
            ->first()
        : null;

    $blocks = collect($page?->activeBlocks ?? []);
    $heroBlock = $blocks->firstWhere('name', 'home_hero') ?? $blocks->firstWhere('type', 'hero');
    $storyBlock = $blocks->firstWhere('name', 'home_story') ?? $blocks->firstWhere('type', 'editorial');
    $bannerBlock = $blocks->firstWhere('name', 'home_banner') ?? $blocks->firstWhere('type', 'editorial_banner');

    $heroImage = media_url($heroBlock?->image_path);
    $heroMobileImage = media_url($heroBlock?->mobile_image_path);
    $storyImage = media_url($storyBlock?->image_path);
    $bannerImage = media_url($bannerBlock?->image_path);

    $catalogUrl = Route::has('storefront.catalog.index')
        ? route('storefront.catalog.index', $contextParams)
        : route('storefront.home', $contextParams);

    $resolveBlockUrl = function ($block, ?string $fallback = null) use ($contextParams, $catalogUrl) {
        $url = trim((string) ($block?->button_url ?? ''));

        if ($url === '') {
            return $fallback ?: $catalogUrl;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            $path = trim($url, '/');

            if ($path === 'catalog' && Route::has('storefront.catalog.index')) {
                return route('storefront.catalog.index', $contextParams);
            }

            return url($url);
        }

        return $url;
    };

    $rootCategories = collect($childrenCategories ?? []);

    if ($rootCategories->isEmpty() && $store) {
        try {
            $rootCategories = app(CatalogRepository::class)->getRootCategories($store, $locale);
        } catch (Throwable $exception) {
            $rootCategories = collect();
        }
    }

    $categoryChildren = function (array $category) use ($store, $locale) {
        if (!$store || empty($category['fam_code'])) {
            return collect();
        }

        try {
            return app(CatalogRepository::class)
                ->getChildrenCategories($store, $locale, $category['fam_code'])
                ->take(4)
                ->pluck('label')
                ->filter()
                ->values();
        } catch (Throwable $exception) {
            return collect();
        }
    };

    $findCategoryUrl = function (array $keywords) use ($rootCategories, $store, $locale, $contextParams, $catalogUrl) {
        $match = $rootCategories->first(function (array $category) use ($keywords) {
            $label = mb_strtolower((string) ($category['label'] ?? ''));

            foreach ($keywords as $keyword) {
                if (str_contains($label, mb_strtolower($keyword))) {
                    return true;
                }
            }

            return false;
        });

        if (!$match && $store) {
            foreach ($rootCategories as $category) {
                try {
                    $child = app(CatalogRepository::class)
                        ->getChildrenCategories($store, $locale, $category['fam_code'] ?? null)
                        ->first(function (array $childCategory) use ($keywords) {
                            $label = mb_strtolower((string) ($childCategory['label'] ?? ''));

                            foreach ($keywords as $keyword) {
                                if (str_contains($label, mb_strtolower($keyword))) {
                                    return true;
                                }
                            }

                            return false;
                        });

                    if ($child) {
                        $match = $child;
                        break;
                    }
                } catch (Throwable $exception) {
                    continue;
                }
            }
        }

        return !empty($match['slug']) && Route::has('storefront.category.show')
            ? route('storefront.category.show', array_merge(['slug' => $match['slug']], $contextParams))
            : $catalogUrl;
    };

    $useCards = [
        [
            'label' => 'Agenda giornaliera',
            'text' => 'Una pagina per ogni giorno',
            'icon' => '01',
            'url' => $findCategoryUrl(['giornalier']),
        ],
        [
            'label' => 'Agenda settimanale',
            'text' => 'La settimana sempre sotto controllo',
            'icon' => '02',
            'url' => $findCategoryUrl(['settiman']),
        ],
        [
            'label' => 'Taccuino righe',
            'text' => 'Per appunti, note e lavoro',
            'icon' => '03',
            'url' => $findCategoryUrl(['righe']),
        ],
        [
            'label' => 'Taccuino puntini',
            'text' => 'Layout leggero e flessibile',
            'icon' => '04',
            'url' => $findCategoryUrl(['puntini']),
        ],
        [
            'label' => 'Pagine bianche',
            'text' => 'Spazio libero per idee e schizzi',
            'icon' => '05',
            'url' => $findCategoryUrl(['bianche']),
        ],
    ];

    $categoryIcon = function (string $label): string {
        $lower = mb_strtolower($label);

        return match (true) {
            str_contains($lower, 'agend') => 'fa-regular fa-calendar',
            str_contains($lower, 'accessor') => 'fa-regular fa-circle-dot',
            str_contains($lower, 'special') => 'fa-regular fa-star',
            str_contains($lower, 'taccuin'),
            str_contains($lower, 'quadern') => 'fa-regular fa-bookmark',
            default => 'fa-regular fa-square',
        };
    };

    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $featuredProducts = collect($products?->items() ?? [])
        ->shuffle()
        ->take(4)
        ->values();

    $formatPrice = function (ProductCardViewModel $card): string {
        if ($card->price === null) {
            return '—';
        }

        return '€ ' . number_format((float) $card->price, 2, ',', '.');
    };
@endphp

@section('title', $page?->meta_title ?: ($store->name ?? 'CIAK'))
@section('meta_description', $page?->meta_description ?: 'Agende, taccuini e accessori CIAK.')

@section('content')
<div class="ciak-home">
    <section class="ciak-hero {{ $heroImage ? 'has-hero-image' : 'has-hero-placeholder' }}" aria-labelledby="ciak-home-title">
        <div class="ciak-hero-copy">
            <span class="ciak-kicker">
                {{ $heroBlock?->subtitle ?: 'CIAK Firenze' }}
            </span>

            <h1 id="ciak-home-title">
                {{ $heroBlock?->title ?: 'Agende e taccuini per ogni giorno' }}
            </h1>

            <p>
                {{ $heroBlock?->content ?: 'Oggetti quotidiani per scrivere, pianificare e portare con te le idee.' }}
            </p>

            <div class="ciak-hero-actions">
                <a
                    href="{{ $resolveBlockUrl($heroBlock, $catalogUrl) }}"
                    class="ciak-button ciak-button-dark"
                    @if($heroBlock?->button_new_tab) target="_blank" rel="noopener noreferrer" @endif
                >
                    {{ $heroBlock?->button_label ?: 'Scopri la collezione' }}
                </a>

                @if(Route::has('storefront.catalog.index'))
                    <a href="{{ $catalogUrl }}" class="ciak-button ciak-button-light">
                        Shop
                    </a>
                @endif
            </div>
        </div>

        <div class="ciak-hero-media">
            @if($heroImage)
                <picture>
                    @if($heroMobileImage)
                        <source media="(max-width: 767px)" srcset="{{ $heroMobileImage }}">
                    @endif

                    <img src="{{ $heroImage }}" alt="{{ $heroBlock?->title ?: 'CIAK' }}" loading="eager" decoding="async">
                </picture>
            @else
                <div class="ciak-hero-placeholder" aria-hidden="true">
                    <span>CIAK</span>
                    <small>Carica qui dal BO un’immagine ambientata</small>
                </div>
            @endif
        </div>
    </section>

    <section class="ciak-use-section" aria-labelledby="ciak-use-title">
        <div class="ciak-section-heading ciak-section-heading-compact">
            <span class="ciak-kicker">Scegli per uso</span>
            <h2 id="ciak-use-title">Trova il formato giusto.</h2>
        </div>

        <div class="ciak-use-grid">
            @foreach($useCards as $card)
                <a href="{{ $card['url'] }}" class="ciak-use-card">
                    <span class="ciak-use-index" aria-hidden="true">{{ $card['icon'] }}</span>
                    <span>
                        <strong>{{ $card['label'] }}</strong>
                        <small>{{ $card['text'] }}</small>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="ciak-collections-section" aria-labelledby="ciak-collections-title">
        <div class="ciak-section-heading ciak-section-heading-row">
            <div>
                <span class="ciak-kicker">Collezioni</span>
                <h2 id="ciak-collections-title">Scegli il tuo mondo CIAK.</h2>
            </div>

            <a href="{{ $catalogUrl }}" class="ciak-text-link">Vedi tutto</a>
        </div>

        <div class="ciak-collection-grid">
            @forelse($rootCategories->take(4) as $category)
                @php
                    $label = trim((string) ($category['label'] ?? 'Categoria'));
                    $slug = $category['slug'] ?? null;
                    $children = $categoryChildren($category);
                @endphp

                <a
                    href="{{ $slug ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) : $catalogUrl }}"
                    class="ciak-collection-card"
                >
                    <span class="ciak-collection-icon">
                        <i class="{{ $categoryIcon($label) }}" aria-hidden="true"></i>
                    </span>

                    <span>
                        <strong>{{ $label }}</strong>

                        @if($children->isNotEmpty())
                            <small>{{ $children->implode(' · ') }}</small>
                        @endif
                    </span>
                </a>
            @empty
                <div class="ciak-empty-state">
                    Nessuna categoria disponibile.
                </div>
            @endforelse
        </div>
    </section>

    @if($bannerBlock && ($bannerImage || $bannerBlock->title || $bannerBlock->content))
        <section class="ciak-editorial-banner {{ $bannerImage ? 'has-image' : '' }}">
            @if($bannerImage)
                <img src="{{ $bannerImage }}" alt="{{ $bannerBlock->title ?: 'CIAK' }}" loading="lazy">
            @endif

            <div>
                @if($bannerBlock->subtitle)
                    <span class="ciak-kicker">{{ $bannerBlock->subtitle }}</span>
                @endif

                @if($bannerBlock->title)
                    <h2>{{ $bannerBlock->title }}</h2>
                @endif

                @if($bannerBlock->content)
                    <p>{{ $bannerBlock->content }}</p>
                @endif

                @if($bannerBlock->button_label)
                    <a
                        href="{{ $resolveBlockUrl($bannerBlock, $catalogUrl) }}"
                        class="ciak-button ciak-button-dark"
                        @if($bannerBlock->button_new_tab) target="_blank" rel="noopener noreferrer" @endif
                    >
                        {{ $bannerBlock->button_label }}
                    </a>
                @endif
            </div>
        </section>
    @endif

    <section class="ciak-featured-section" aria-labelledby="ciak-featured-title">
        <div class="ciak-section-heading ciak-section-heading-row">
            <div>
                <span class="ciak-kicker">Selezione</span>
                <h2 id="ciak-featured-title">Scelti per iniziare.</h2>
            </div>

            <a href="{{ $catalogUrl }}" class="ciak-text-link">
                {{ ($products?->total() ?? 0) > 0 ? number_format($products->total(), 0, ',', '.') . ' prodotti' : 'Vai allo shop' }}
            </a>
        </div>

        @if($featuredProducts->isEmpty())
            <div class="ciak-empty-state">
                Nessun prodotto disponibile al momento.
            </div>
        @else
            <div class="ciak-product-grid">
                @foreach($featuredProducts as $product)
                    @php
                        $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                        $card = ProductCardViewModel::make($product, $listingCard);
                        $hasHoverImage = $card->hoverImage && $card->hoverImage !== $card->image;
                    @endphp

                    <article class="ciak-product-card">
                        <a href="{{ $card->productUrl }}" class="ciak-product-media {{ $hasHoverImage ? 'has-hover-image' : '' }}">
                            @if($card->image)
                                <img src="{{ $card->image }}" alt="{{ $card->name }}" loading="lazy" class="ciak-product-image-primary">

                                @if($hasHoverImage)
                                    <img src="{{ $card->hoverImage }}" alt="{{ $card->name }}" loading="lazy" class="ciak-product-image-hover">
                                @endif
                            @else
                                <span class="ciak-product-empty">CIAK</span>
                            @endif
                        </a>

                        <div class="ciak-product-body">
                            <a href="{{ $card->productUrl }}" class="ciak-product-title">
                                {{ $card->name }}
                            </a>

                            <div class="ciak-product-meta">
                                <span>{{ $card->selectedFormatValue ?: 'CIAK' }}</span>
                                <strong>{{ $formatPrice($card) }}</strong>
                            </div>

                            @if($card->colorOptions->isNotEmpty())
                                <div class="ciak-product-swatches" aria-label="Colori disponibili">
                                    @foreach($card->colorOptions->take(6) as $option)
                                        @php($payload = $card->colorOptionPayload($option))
                                        <span title="{{ $payload['value'] ?? '' }}">
                                            @if(!empty($payload['swatch_url']))
                                                <img src="{{ $payload['swatch_url'] }}" alt="{{ $payload['value'] ?? '' }}">
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="ciak-story-section">
        <div>
            <span class="ciak-kicker">
                {{ $storyBlock?->subtitle ?: 'Dettagli CIAK' }}
            </span>

            <h2>
                {{ $storyBlock?->title ?: 'La copertina morbida, l’elastico, il colore.' }}
            </h2>

            @if($storyBlock?->content)
                <p>{{ $storyBlock->content }}</p>
            @endif
        </div>

        <div class="ciak-story-media">
            @if($storyImage)
                <img src="{{ $storyImage }}" alt="{{ $storyBlock?->title ?: 'CIAK' }}" loading="lazy">
            @else
                <div class="ciak-story-placeholder">
                    <span>Immagine editoriale</span>
                    <small>Caricabile dal BO</small>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection
