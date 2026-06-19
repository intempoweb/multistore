{{-- resources/views/storefront/themes/b2c/ciak/overrides/home.blade.php --}}
@extends($storefrontLayout)

@section('title', 'CIAK - Agende e taccuini fatti a mano in Italia')

@section('content')
@php
    use App\Models\ProductCardViewModel;
    use App\Repositories\Storefront\CatalogRepository;
    use Illuminate\Support\Str;

    $listingCardsByProductSku = collect($listingCardsByProductSku ?? []);
    $productsCollection = collect($products?->items() ?? []);

    $agentContextId = (string) request('agent_context', '');
    $contextParams = $agentContextId !== '' ? ['agent_context' => $agentContextId] : [];

    $catalogUrl = Route::has('storefront.catalog.index')
        ? route('storefront.catalog.index', $contextParams)
        : route('storefront.home', $contextParams);

    $rootCategories = collect();

    try {
        $rootCategories = app(CatalogRepository::class)
            ->getRootCategories($store, $locale ?? app()->getLocale())
            ->values();
    } catch (\Throwable $exception) {
        $rootCategories = collect();
    }

    $childrenByRoot = $rootCategories->mapWithKeys(function (array $category) use ($store, $locale) {
        $famCode = trim((string) ($category['fam_code'] ?? ''));

        if ($famCode === '') {
            return [$famCode => collect()];
        }

        try {
            $children = app(CatalogRepository::class)
                ->getChildrenCategories($store, $locale ?? app()->getLocale(), $famCode)
                ->values();
        } catch (\Throwable $exception) {
            $children = collect();
        }

        return [$famCode => $children];
    });

    $categoryUrl = static function (?array $category) use ($contextParams, $catalogUrl): string {
        $slug = $category['slug'] ?? null;

        return $slug && Route::has('storefront.category.show')
            ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams))
            : $catalogUrl;
    };

    $findCategory = static function (string $needle, ?string $rootCode = null) use ($rootCategories, $childrenByRoot) {
        $needle = Str::lower($needle);
        $candidates = $rootCode
            ? collect($childrenByRoot->get($rootCode, []))
            : $rootCategories->merge($childrenByRoot->flatten(1));

        return $candidates->first(function ($category) use ($needle) {
            return str_contains(Str::lower((string) ($category['label'] ?? '')), $needle);
        });
    };

    $agendeRoot = $rootCategories->first(fn ($category) => str_contains(Str::lower((string) ($category['label'] ?? '')), 'agende'));
    $taccuiniRoot = $rootCategories->first(fn ($category) => str_contains(Str::lower((string) ($category['label'] ?? '')), 'taccuini'));

    $iconShortcuts = collect([
        [
            'label' => 'Agenda giornaliera',
            'text' => 'Una pagina per ogni giorno',
            'icon' => 'fa-regular fa-calendar-days',
            'category' => $findCategory('giornaliere', $agendeRoot['fam_code'] ?? null),
        ],
        [
            'label' => 'Agenda settimanale',
            'text' => 'La settimana sempre sotto controllo',
            'icon' => 'fa-regular fa-calendar-check',
            'category' => $findCategory('settimanali', $agendeRoot['fam_code'] ?? null),
        ],
        [
            'label' => 'Taccuino righe',
            'text' => 'Per note, studio e lavoro',
            'icon' => 'fa-solid fa-align-left',
            'category' => $findCategory('righe', $taccuiniRoot['fam_code'] ?? null),
        ],
        [
            'label' => 'Taccuino puntini',
            'text' => 'Layout leggero e flessibile',
            'icon' => 'fa-solid fa-braille',
            'category' => $findCategory('puntini', $taccuiniRoot['fam_code'] ?? null),
        ],
        [
            'label' => 'Pagine bianche',
            'text' => 'Spazio libero per idee e schizzi',
            'icon' => 'fa-regular fa-file',
            'category' => $findCategory('pagine bianche', $taccuiniRoot['fam_code'] ?? null),
        ],
    ])->map(function (array $item) use ($categoryUrl, $catalogUrl) {
        $item['url'] = $item['category'] ? $categoryUrl($item['category']) : $catalogUrl;

        return $item;
    });

    $categoryCards = $rootCategories->map(function (array $category) use ($childrenByRoot, $categoryUrl) {
        $label = (string) ($category['label'] ?? 'Categoria');
        $famCode = (string) ($category['fam_code'] ?? '');
        $children = collect($childrenByRoot->get($famCode, []));

        $icon = match (true) {
            str_contains(Str::lower($label), 'agende') => 'fa-regular fa-calendar',
            str_contains(Str::lower($label), 'taccuini') => 'fa-regular fa-note-sticky',
            str_contains(Str::lower($label), 'speciali') => 'fa-regular fa-star',
            str_contains(Str::lower($label), 'accessori') => 'fa-regular fa-bookmark',
            default => 'fa-regular fa-folder',
        };

        return [
            'label' => Str::headline($label),
            'url' => $categoryUrl($category),
            'icon' => $icon,
            'children' => $children->take(4)->map(fn ($child) => Str::headline((string) ($child['label'] ?? '')))->filter()->values(),
        ];
    });

    $heroProducts = collect();
    $usedHeroSkus = collect();

    foreach ($rootCategories as $category) {
        if ($heroProducts->count() >= 4) {
            break;
        }

        $famCode = trim((string) ($category['fam_code'] ?? ''));

        if ($famCode === '') {
            continue;
        }

        try {
            $categoryProducts = app(CatalogRepository::class)->getCategoryProducts(
                $store,
                $locale ?? app()->getLocale(),
                $famCode,
                null,
                null,
                null,
                null,
                null,
                18,
                [],
                'default'
            );
        } catch (\Throwable $exception) {
            continue;
        }

        $picked = collect($categoryProducts->items())
            ->filter(fn ($product) => !empty($product->main_image_url) && !$usedHeroSkus->contains((string) $product->sku))
            ->shuffle()
            ->first();

        if ($picked) {
            $heroProducts->push($picked);
            $usedHeroSkus->push((string) $picked->sku);
        }
    }

    if ($heroProducts->count() < 4) {
        $heroProducts = $heroProducts
            ->merge(
                $productsCollection
                    ->filter(fn ($product) => !empty($product->main_image_url))
                    ->reject(fn ($product) => $usedHeroSkus->contains((string) $product->sku))
                    ->shuffle()
                    ->take(4 - $heroProducts->count())
            )
            ->values();
    }

    $newProducts = $productsCollection
        ->filter(fn ($product) => (bool) ($product->flgnovita_webt01 ?? false))
        ->values();

    $featuredProducts = $newProducts->isNotEmpty()
        ? $newProducts->shuffle()->take(4)->values()
        : $productsCollection
            ->filter(fn ($product) => !empty($product->main_image_url))
            ->shuffle()
            ->take(4)
            ->values();

    if ($featuredProducts->isEmpty()) {
        $featuredProducts = $productsCollection->shuffle()->take(4)->values();
    }

    $productsTotal = $products?->total() ?? $productsCollection->count();
@endphp

<div class="ciak-home">
    <section class="ciak-hero" aria-labelledby="ciak-home-title">
        <div class="ciak-hero-copy">
            <span class="ciak-eyebrow">Made in Italy</span>

            <h1 id="ciak-home-title">Agende e taccuini per ogni giorno.</h1>

            <p>
                Colori pieni, copertine morbide e l'iconico elastico orizzontale.
                Scopri il mondo CIAK: essenziale, tattile, pensato per accompagnare studio, lavoro e viaggio.
            </p>

            <div class="ciak-hero-actions">
                <a href="{{ $catalogUrl }}" class="btn ciak-btn ciak-btn-dark">
                    Scopri la collezione
                </a>

                @if($agendeRoot)
                    <a href="{{ $categoryUrl($agendeRoot) }}" class="btn ciak-btn ciak-btn-light">
                        Agende 2026
                    </a>
                @endif
            </div>
        </div>

        <div class="ciak-hero-visual" aria-label="Prodotti CIAK">
            @forelse($heroProducts as $product)
                @php
                    $listingCard = collect($listingCardsByProductSku->get((string) $product->sku, []));
                    $card = ProductCardViewModel::make($product, $listingCard);
                @endphp

                <a href="{{ $card->productUrl }}" class="ciak-hero-product ciak-hero-product-{{ $loop->iteration }}">
                    @if($card->image)
                        <img src="{{ $card->image }}" alt="{{ $card->name }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                    @else
                        <i class="fa-regular fa-note-sticky" aria-hidden="true"></i>
                    @endif
                </a>
            @empty
                <span class="ciak-hero-placeholder">
                    <i class="fa-regular fa-note-sticky" aria-hidden="true"></i>
                </span>
            @endforelse
        </div>
    </section>

    <section class="ciak-icon-section" aria-labelledby="ciak-shop-by-use-title">
        <div class="ciak-section-head">
            <div>
                <span class="ciak-eyebrow">Scegli per uso</span>
                <h2 id="ciak-shop-by-use-title">Trova il formato giusto</h2>
            </div>
        </div>

        <div class="ciak-icon-grid">
            @foreach($iconShortcuts as $shortcut)
                <a href="{{ $shortcut['url'] }}" class="ciak-icon-card">
                    <i class="{{ $shortcut['icon'] }}" aria-hidden="true"></i>
                    <span>
                        <strong>{{ $shortcut['label'] }}</strong>
                        <small>{{ $shortcut['text'] }}</small>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="ciak-home-section" aria-labelledby="ciak-categories-title">
        <div class="ciak-section-head">
            <div>
                <span class="ciak-eyebrow">Shop</span>
                <h2 id="ciak-categories-title">Collezioni</h2>
            </div>

            <a href="{{ $catalogUrl }}" class="ciak-text-link">
                Vedi tutto
            </a>
        </div>

        <div class="ciak-category-grid">
            @foreach($categoryCards as $category)
                <a href="{{ $category['url'] }}" class="ciak-category-card">
                    <span class="ciak-category-icon">
                        <i class="{{ $category['icon'] }}" aria-hidden="true"></i>
                    </span>

                    <span>
                        <strong>{{ $category['label'] }}</strong>

                        @if($category['children']->isNotEmpty())
                            <small>{{ $category['children']->implode(' · ') }}</small>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="ciak-home-section" aria-labelledby="ciak-featured-title">
        <div class="ciak-section-head">
            <div>
                <span class="ciak-eyebrow">Novita e bestseller</span>
                <h2 id="ciak-featured-title">In evidenza</h2>
            </div>

            <a href="{{ $catalogUrl }}" class="ciak-text-link">
                {{ $productsTotal > 0 ? number_format($productsTotal, 0, ',', '.') . ' prodotti' : 'Vai allo shop' }}
            </a>
        </div>

        @if($featuredProducts->isEmpty())
            <div class="ciak-empty-state">
                Nessun prodotto disponibile al momento.
            </div>
        @else
            <div class="row g-3 g-xl-4">
                @foreach($featuredProducts as $product)
                    <div class="col-12 col-sm-6 col-xl-3">
                        @include('storefront.base.partials.product-card', [
                            'product' => $product,
                            'listingCard' => $listingCardsByProductSku->get((string) $product->sku, []),
                            'agentContextId' => $agentContextId,
                            'contextParams' => $contextParams,
                        ])
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="ciak-story-band" aria-labelledby="ciak-story-title">
        <div>
            <span class="ciak-eyebrow">Dettagli CIAK</span>
            <h2 id="ciak-story-title">Un oggetto semplice, curato nei materiali.</h2>
        </div>

        <div class="ciak-story-grid">
            <span>
                <strong>Elastico orizzontale</strong>
                <small>Una firma visiva riconoscibile.</small>
            </span>

            <span>
                <strong>Colori pieni</strong>
                <small>Dalle tinte classiche alle collezioni stagionali.</small>
            </span>

            <span>
                <strong>Carta e formato</strong>
                <small>Scelte pratiche per scrivere, pianificare e disegnare.</small>
            </span>
        </div>
    </section>
</div>
@endsection
