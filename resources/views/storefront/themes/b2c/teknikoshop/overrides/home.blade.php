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

    $collectionCards = $collectionDefinitions
        ->map(function (array $definition) use ($collectionItems, $collectionRoot, $collectionAssetsFor) {
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
            ];
        })
        ->filter(fn ($item) => filled($item['slug'] ?? null) && !empty($item['assets']))
        ->values();

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
        <section class="ciak-section ciak-shell teknikoshop-collections-section">
            <header class="ciak-section-heading teknikoshop-collections-heading">
                <p class="ciak-eyebrow">{{ trim((string) ($categoriesIntro?->subtitle ?? '')) ?: 'Collezioni' }}</p>
                <h2>{{ trim((string) ($categoriesIntro?->title ?? '')) ?: 'Esplora ' . $publicStoreName }}</h2>
                @if(filled($categoriesIntro?->content))
                    <p>{{ $categoriesIntro->content }}</p>
                @else
                    <p>Linee tecniche, materiali resistenti e formati progettati per lavoro, studio e viaggio.</p>
                @endif
            </header>
            <div class="ciak-category-grid teknikoshop-collections-grid">
                @foreach($collectionCards as $category)
                    @php
                        $category = (array) $category;
                        $childCount = collect($category['children'] ?? [])->filter(fn ($item) => !empty($item['slug']))->count();
                        $assets = $category['assets'] ?? [];
                    @endphp
                    <a class="ciak-category-card teknikoshop-collection-card" href="{{ route('storefront.category.show', array_merge(['slug' => $category['slug']], $contextParams ?? [])) }}">
                        @if(!empty($assets['image']) && !empty($assets['outline']))
                            <span class="teknikoshop-collection-visual" data-teknikoshop-collection-visual>
                                <span
                                    class="teknikoshop-collection-outline"
                                    data-teknikoshop-outline-src="{{ $assets['outline'] }}"
                                    aria-hidden="true"
                                ></span>
                                <img class="teknikoshop-collection-outline-fallback" src="{{ $assets['outline'] }}" alt="" loading="lazy" decoding="async">
                                <img class="teknikoshop-collection-photo" src="{{ $assets['image'] }}" alt="" loading="lazy" decoding="async">
                            </span>
                        @endif
                        <span class="teknikoshop-collection-copy">
                            <strong>{{ $category['label'] }}</strong>
                            <small>{{ $childCount > 0 ? $childCount . ' selezioni disponibili' : $technicalSummaryFor($category) }}</small>
                        </span>
                        <i data-lucide="arrow-up-right"></i>
                    </a>
                @endforeach
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
