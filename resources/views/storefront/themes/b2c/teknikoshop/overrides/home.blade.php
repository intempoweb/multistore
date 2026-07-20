@extends($storefrontLayout)

@php
    $publicStoreName = trim((string) preg_replace('/\bB2[BC]\b\s*/i', '', (string) ($store?->name ?? 'TEKNIKO'))) ?: 'TEKNIKO';
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
    $collectionNavigation = collect($navigationTree ?? [])->isNotEmpty()
        ? collect($navigationTree ?? [])
        : collect($rootCategories ?? []);
    $collectionRoot = $collectionNavigation->first();
    $collectionItems = collect($collectionRoot['children'] ?? [])->filter(fn ($item) => !empty($item['slug']))->values();

    if ($collectionItems->isEmpty()) {
        $collectionItems = $collectionNavigation->filter(fn ($item) => !empty($item['slug']))->values();
    }

    $collectionDefinitions = collect([
        ['key' => 'led', 'label' => 'LED'],
        ['key' => 'expand', 'label' => 'EXPAND'],
        ['key' => 'magnum', 'label' => 'MAGNUM'],
        ['key' => 'big', 'label' => 'BIG'],
        ['key' => 'tab', 'label' => 'TAB'],
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

        $imageName = match ($key) {
            'big' => 'big.png',
            'magnum' => 'magnum_.jpg',
            default => $key . '.jpg',
        };

        return [
            'key' => $key,
            'image' => b2c_theme_asset_url("teknikoshop/collections/{$imageName}"),
            'outline' => b2c_theme_asset_url("teknikoshop/collections/{$key}_outline.svg"),
        ];
    };
    $technicalSummaryFor = static function (array $item): string {
        $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

        return match (true) {
            str_contains($text, 'led') => 'Profilo leggero, dettagli essenziali e uso quotidiano.',
            str_contains($text, 'expand') => 'Spazio modulabile per organizzare lavoro e viaggio.',
            str_contains($text, 'magnum') => 'Capienza superiore e struttura resistente.',
            str_contains($text, 'big') => 'Formato ampio per notebook, documenti e accessori.',
            str_contains($text, 'tab') => 'Compatto e pratico per tecnologia e mobilita.',
            str_contains($text, 'zain') => 'Collezioni tecniche pensate per muoversi ogni giorno.',
            default => 'Soluzione tecnica per accompagnare lavoro e tempo libero.',
        };
    };
    $technicalSpecsFor = static function (array $item): array {
        $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

        return match (true) {
            str_contains($text, 'led') => ['Profilo compatto', 'Tasche essenziali', 'Uso quotidiano'],
            str_contains($text, 'expand') => ['Volume espandibile', 'Organizzazione interna', 'Viaggio e lavoro'],
            str_contains($text, 'magnum') => ['Capienza superiore', 'Struttura stabile', 'Massima resistenza'],
            str_contains($text, 'big') => ['Formato ampio', 'Notebook e documenti', 'Accessori sempre ordinati'],
            str_contains($text, 'tab') => ['Compatto', 'Tecnologia protetta', 'Mobilita urbana'],
            default => ['Design tecnico', 'Materiali resistenti', 'Uso quotidiano'],
        };
    };

    $collectionCards = $collectionDefinitions
        ->map(function (array $definition) use ($collectionItems, $collectionRoot, $collectionAssetsFor, $technicalSummaryFor, $technicalSpecsFor) {
            $matchedCategory = $collectionItems->first(function ($item) use ($definition) {
                $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

                return str_contains($text, $definition['key']);
            });
            $matchedCategory = $matchedCategory ? (array) $matchedCategory : [];
            $fallbackSlug = trim((string) ($collectionRoot['slug'] ?? ''));

            return [
                'label' => $matchedCategory['label'] ?? $definition['label'],
                'slug' => $matchedCategory['slug'] ?? $fallbackSlug,
                'children' => $matchedCategory['children'] ?? [],
                'assets' => $collectionAssetsFor(['label' => $definition['label'], 'slug' => $definition['key']]),
                'description' => $technicalSummaryFor(['label' => $definition['label'], 'slug' => $definition['key']]),
                'specs' => $technicalSpecsFor(['label' => $definition['label'], 'slug' => $definition['key']]),
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

                                <a href="{{ route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams ?? [])) }}">Scopri la selezione<i data-lucide="arrow-right"></i></a>
                            </div>

                            <div class="ciak-format-stage">
                                <div class="ciak-format-visual teknikoshop-format-visual" aria-hidden="true">
                                    <span
                                        class="ciak-format-outline-layer teknikoshop-format-outline-layer"
                                        data-teknikoshop-outline-src="{{ $assets['outline'] ?? $assets['image'] }}"
                                    ></span>
                                    <img class="ciak-format-visual-outline-fallback" src="{{ $assets['outline'] ?? $assets['image'] }}" alt="" loading="lazy" decoding="async">
                                    <img class="ciak-format-visual-color" src="{{ $assets['image'] ?? $assets['outline'] }}" alt="" loading="lazy" decoding="async">
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
