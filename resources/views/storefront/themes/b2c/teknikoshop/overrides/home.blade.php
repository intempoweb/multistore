@extends($storefrontLayout)

@php
    $publicStoreName = trim((string) preg_replace('/\bB2[BC]\b\s*/i', '', (string) ($store?->name ?? 'TEKNIKO'))) ?: 'TEKNIKO';
    $contextParams = $contextParams ?? [];
    $blocksByName = collect($storefrontPageBlocks ?? [])->keyBy(fn ($block) => (string) $block->name);
    $hero = $blocksByName->get('home_hero');
    $heroTitle = trim((string) ($hero?->title ?? '')) ?: $publicStoreName;
    $heroSubtitle = trim((string) ($hero?->subtitle ?? '')) ?: $publicStoreName;
    $heroContent = trim((string) ($hero?->content ?? '')) ?: 'Zaini, accessori e soluzioni pratiche per lavoro, studio e viaggio.';
    $heroButtonLabel = trim((string) ($hero?->button_label ?? '')) ?: 'Scopri la collezione';
    $heroButtonUrl = filled($hero?->button_url)
        ? (str_starts_with((string) $hero->button_url, '/') ? url($hero->button_url) : $hero->button_url)
        : route('storefront.catalog.index', $contextParams ?? []);
    $categoriesIntro = $blocksByName->get('home_categories_intro');
    $featuredIntro = $blocksByName->get('home_featured_intro');
    $featuredProducts = collect(method_exists($products, 'items') ? $products->items() : ($products ?? []))->take(8);
    $catalogRepository = app(\App\Repositories\Storefront\CatalogRepository::class);
    $locale = app()->getLocale();
    $collectionNavigation = collect($navigationTree ?? [])->isNotEmpty()
        ? collect($navigationTree ?? [])
        : collect($rootCategories ?? []);
    $collectionRoot = $collectionNavigation->first();
    $collectionItems = collect($collectionRoot['children'] ?? [])->filter(fn ($item) => !empty($item['slug']))->values();

    if ($collectionItems->isEmpty()) {
        $collectionItems = $collectionNavigation->filter(fn ($item) => !empty($item['slug']))->values();
    }

    $collectionDefinitions = collect([
        [
            'key' => 'led',
            'label' => 'LED',
            'fam' => 'TZ',
            'sfam' => 'TZ01',
            'image' => 'led.jpg',
            'outline' => 'led_outline.svg',
            'description' => 'Zaino tecnico con luce LED ad alta luminosita, pensato per coniugare sicurezza, tecnologia e mobilita quotidiana.',
            'specs' => ['LED 3 modalità', 'TSA e cavo in acciaio', 'USB esterna', 'Tessuto antistrappo'],
            'callouts' => ['LED 3 modalità', 'Chiusura TSA', 'Tessuto antistrappo'],
        ],
        [
            'key' => 'expand',
            'label' => 'EXPAND',
            'fam' => 'TZ',
            'sfam' => 'TZ02',
            'image' => 'expand.jpg',
            'outline' => 'expand_outline.svg',
            'description' => 'Zaino porta PC antifurto con capacita espandibile tramite zip perimetrale, progettato per lavoro e viaggio.',
            'specs' => ['Capacita espandibile', 'TSA e cavo in acciaio', 'USB esterna', 'Tessuto idrorepellente'],
            'callouts' => ['Capacita espandibile', 'TSA e cavo in acciaio', 'Tessuto idrorepellente'],
        ],
        [
            'key' => 'magnum',
            'label' => 'MAGNUM',
            'fam' => 'TZ',
            'sfam' => 'TZ03',
            'image' => 'magnum_.jpg',
            'outline' => 'magnum_outline.svg',
            'description' => 'Formato ad alta capienza per portare notebook, documenti e accessori con struttura solida e organizzata.',
            'specs' => ['Capienza superiore', 'Porta PC', 'Struttura resistente', 'Organizzazione interna'],
            'callouts' => ['Capienza superiore', 'Organizzazione interna', 'Struttura resistente'],
        ],
        [
            'key' => 'big',
            'label' => 'BIG',
            'fam' => 'TZ',
            'sfam' => 'TZ04',
            'image' => 'big.png',
            'outline' => 'big_outline.svg',
            'description' => 'Zaino antifurto Tekniko Big con cavo antifurto, chiusura a combinazione e porta USB.',
            'specs' => ['Cavo antifurto', 'Chiusura a combinazione', 'Porta USB', 'Formato ampio'],
            'callouts' => ['Chiusura a combinazione', 'Cavo antifurto', 'Porta USB'],
        ],
        [
            'key' => 'tab',
            'label' => 'TAB',
            'fam' => 'TZ',
            'sfam' => 'TZ05',
            'image' => 'tab.jpg',
            'outline' => 'tab_outline.svg',
            'description' => 'Zaino compatto per tecnologia e mobilita, pratico per accompagnare studio, lavoro e spostamenti urbani.',
            'specs' => ['Formato compatto', 'Protezione tecnologia', 'Uso quotidiano', 'Mobilita urbana'],
            'callouts' => ['Formato compatto', 'Tecnologia protetta', 'Mobilita urbana'],
        ],
    ]);

    $collectionKeyFor = static function (array $item): ?string {
        $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

        return match (true) {
            str_contains($text, 'led') => 'led',
            str_contains($text, 'expand') => 'expand',
            str_contains($text, 'magnum') => 'magnum',
            str_contains($text, 'big') => 'big',
            str_contains($text, 'tab') => 'tab',
            default => null,
        };
    };

    $collectionAssetsFor = static function (array $item) use ($collectionKeyFor): array {
        $key = $collectionKeyFor($item);

        if (!$key) {
            return [];
        }

        $definition = collect([
            'led' => ['image' => 'led.jpg', 'outline' => 'led_outline.svg'],
            'expand' => ['image' => 'expand.jpg', 'outline' => 'expand_outline.svg'],
            'magnum' => ['image' => 'magnum_.jpg', 'outline' => 'magnum_outline.svg'],
            'big' => ['image' => 'big.png', 'outline' => 'big_outline.svg'],
            'tab' => ['image' => 'tab.jpg', 'outline' => 'tab_outline.svg'],
        ])->get($key);

        return [
            'key' => $key,
            'image' => b2c_theme_asset_url("teknikoshop/collections/{$definition['image']}"),
            'outline' => b2c_theme_asset_url("teknikoshop/collections/{$definition['outline']}"),
        ];
    };
    $collectionCards = $collectionDefinitions
        ->map(function (array $definition) use ($collectionItems, $collectionAssetsFor, $catalogRepository, $contextParams, $locale, $store) {
            $matchedCategory = $collectionItems->first(function ($item) use ($definition) {
                $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

                return str_contains($text, $definition['key']);
            });
            $matchedCategory = $matchedCategory ? (array) $matchedCategory : [];
            $dynamicSlug = $store
                ? $catalogRepository->buildCategorySlug($store, $locale, $definition['fam'], $definition['sfam'])
                : '';
            $slug = $matchedCategory['slug'] ?? $dynamicSlug;

            return [
                'label' => $matchedCategory['label'] ?? $definition['label'],
                'slug' => $slug,
                'children' => $matchedCategory['children'] ?? [],
                'assets' => $collectionAssetsFor(['label' => $definition['label'], 'slug' => $definition['key']]),
                'url' => route('storefront.category.show', array_merge(['slug' => $slug], $contextParams ?? [])),
                'description' => $definition['description'],
                'specs' => $definition['specs'],
                'callouts' => $definition['callouts'],
                'key' => $definition['key'],
            ];
        })
        ->filter(fn ($item) => filled($item['slug'] ?? null) && !empty($item['assets']))
        ->values();
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

    @if($collectionCards->isNotEmpty())
        <section class="ciak-format-section teknikoshop-format-section" data-teknikoshop-formats aria-labelledby="teknikoshop-collections-title">
            <div class="ciak-shell">
                <header class="ciak-format-heading teknikoshop-format-heading">
                    <div>
                        <p class="ciak-eyebrow">{{ trim((string) ($categoriesIntro?->subtitle ?? '')) ?: 'Collezioni' }}</p>
                        <h2 id="teknikoshop-collections-title">{{ trim((string) ($categoriesIntro?->title ?? '')) ?: 'Esplora ' . $publicStoreName }}</h2>
                    </div>
                    <p>{{ filled($categoriesIntro?->content) ? $categoriesIntro->content : 'Linee tecniche, materiali resistenti e formati progettati per lavoro, studio e viaggio.' }}</p>
                </header>
            </div>

            <div class="ciak-format-stories-wrapper teknikoshop-format-tabs-wrapper" data-teknikoshop-format-tabs-wrapper>
                <div class="ciak-shell">
                    <div class="ciak-format-stories teknikoshop-format-tabs" role="tablist" aria-label="Collezioni TekNiko">
                        @foreach($collectionCards as $category)
                            @php($assets = $category['assets'] ?? [])
                            <button
                                type="button"
                                class="ciak-format-story teknikoshop-format-tab {{ $loop->first ? 'is-active' : '' }}"
                                role="tab"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                aria-controls="teknikoshop-format-panel-{{ $loop->index }}"
                                id="teknikoshop-format-tab-{{ $loop->index }}"
                                data-teknikoshop-format-tab
                                data-teknikoshop-format-index="{{ $loop->index }}"
                            >
                                <span class="ciak-format-story-ring teknikoshop-format-tab-ring">
                                    <img src="{{ $assets['outline'] ?? $assets['image'] }}" alt="" loading="lazy" decoding="async">
                                </span>
                                <span class="ciak-format-story-label">{{ $category['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="ciak-shell">
                <div class="ciak-format-showcase teknikoshop-format-showcase">
                    @foreach($collectionCards as $category)
                        @php($assets = $category['assets'] ?? [])
                        <article
                            class="ciak-format-panel teknikoshop-format-panel {{ $loop->first ? 'is-active' : '' }}"
                            id="teknikoshop-format-panel-{{ $loop->index }}"
                            role="tabpanel"
                            aria-labelledby="teknikoshop-format-tab-{{ $loop->index }}"
                            data-teknikoshop-format-panel
                            data-teknikoshop-format-index="{{ $loop->index }}"
                            @if(!$loop->first) hidden @endif
                        >
                            <div class="ciak-format-panel-copy">
                                <p class="ciak-eyebrow">Collezione</p>
                                <h3>{{ $category['label'] }}</h3>
                                <p>{{ $category['description'] }}</p>

                                <div class="ciak-format-specs" aria-label="Caratteristiche collezione">
                                    @foreach($category['specs'] as $spec)
                                        <span>{{ $spec }}</span>
                                    @endforeach
                                </div>

                                <a href="{{ $category['url'] }}">Scopri la selezione<i data-lucide="arrow-right"></i></a>
                            </div>

                            <div class="ciak-format-stage teknikoshop-format-stage">
                                <div class="ciak-format-visual teknikoshop-format-visual" aria-hidden="true">
                                    <span
                                        class="ciak-format-outline-layer teknikoshop-format-outline-layer"
                                        data-teknikoshop-outline-src="{{ $assets['outline'] ?? $assets['image'] }}"
                                    ></span>
                                    <img class="ciak-format-visual-outline-fallback" src="{{ $assets['outline'] ?? $assets['image'] }}" alt="" loading="lazy" decoding="async">
                                    <img class="ciak-format-visual-color" src="{{ $assets['image'] ?? $assets['outline'] }}" alt="" loading="lazy" decoding="async">
                                </div>

                                <div class="ciak-format-callouts teknikoshop-format-callouts teknikoshop-format-callouts-{{ $category['key'] }}" aria-hidden="true">
                                    @foreach(array_slice($category['callouts'], 0, 3) as $calloutIndex => $callout)
                                        <span class="ciak-format-callout teknikoshop-format-callout is-{{ ['one', 'two', 'three'][$calloutIndex] ?? 'one' }}">
                                            <span class="ciak-format-callout-dot"></span>
                                            <span class="ciak-format-callout-line"></span>
                                            <span class="ciak-format-callout-label">{{ $callout }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($featuredProducts->isNotEmpty())
        <section class="ciak-featured-section ciak-shell">
            <header class="ciak-section-heading">
                <p class="ciak-eyebrow">{{ trim((string) ($featuredIntro?->subtitle ?? '')) ?: 'In evidenza' }}</p>
                <h2>{{ trim((string) ($featuredIntro?->title ?? '')) ?: 'Prodotti in evidenza' }}</h2>
                <a href="{{ route('storefront.catalog.index', $contextParams ?? []) }}">{{ trim((string) ($featuredIntro?->button_label ?? '')) ?: __('themes_b2c.ciak.view_all') }} <i data-lucide="arrow-right"></i></a>
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
